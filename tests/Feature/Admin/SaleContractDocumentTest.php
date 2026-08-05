<?php

use App\Mail\SaleContractDocumentMail;
use App\Models\Building;
use App\Models\Contract;
use App\Models\Room;
use App\Models\User;
use App\Support\DocumentFilename;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

function saleDocumentAdmin(): User
{
    (new RoleSeeder)->run();
    (new UserSeeder)->run();

    return User::query()->where('email', 'admin@rosewoodroyale.com')->firstOrFail();
}

function seedSaleDocumentStack(): array
{
    $building = Building::query()->create([
        'building_name' => 'Rosewood Tower',
        'location' => 'Yangon',
    ]);

    $room = Room::query()->create([
        'building_id' => $building->id,
        'room_number' => 'A-1201',
        'floor_number' => 12,
        'type' => 'sale',
        'status' => 'available',
        'area_sqft' => 1200,
        'sale_price' => 850000000,
        'rent_price' => 0,
        'rent_deposit_price' => 0,
        'booking_deposit_price' => 85000000,
    ]);

    $customer = User::query()->where('email', 'mgmg@rosewoodroyale.com')->firstOrFail();

    return compact('building', 'room', 'customer');
}

function createSaleDraftContract(User $admin, Room $room, User $customer): Contract
{
    $response = test()->actingAs($admin, 'sanctum')
        ->postJson('/api/sale-contract-drafts', [
            'user_id' => $customer->id,
            'room_id' => $room->id,
            'payment_type' => 'full',
            'start_date' => '2027-07-15',
        ])
        ->assertCreated();

    return Contract::query()->findOrFail($response->json('data.id'));
}

function expectedSalePdfFilename(Contract $contract): string
{
    return DocumentFilename::saleContract(
        $contract->start_date,
        $contract->room?->room_number,
        $contract->contract_number,
    );
}

test('admin can download export and email draft sale contract document', function () {
    Mail::fake();

    $admin = saleDocumentAdmin();
    ['room' => $room, 'customer' => $customer] = seedSaleDocumentStack();
    $contract = createSaleDraftContract($admin, $room, $customer);
    $pdfFilename = expectedSalePdfFilename($contract);

    $this->actingAs($admin, 'sanctum')
        ->get("/api/sale-contract-drafts/{$contract->id}/document/download")
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf')
        ->assertHeader('content-disposition', 'attachment; filename="'.$pdfFilename.'"');

    $this->actingAs($admin, 'sanctum')
        ->get("/api/sale-contract-drafts/{$contract->id}/document/export")
        ->assertOk()
        ->assertHeader('content-type', 'text/html; charset=UTF-8')
        ->assertHeader('content-disposition', 'inline; filename="S-000001.html"');

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/sale-contract-drafts/{$contract->id}/document/email")
        ->assertOk()
        ->assertJsonPath('message', 'Sale contract document sent successfully.');

    Mail::assertSent(SaleContractDocumentMail::class, function (SaleContractDocumentMail $mail) use ($customer, $pdfFilename) {
        return $mail->hasTo($customer->email)
            && $mail->filename === $pdfFilename
            && str_starts_with($mail->documentPdf, '%PDF');
    });
});

test('admin can download export and email approved sale contract document', function () {
    Mail::fake();

    $admin = saleDocumentAdmin();
    ['room' => $room, 'customer' => $customer] = seedSaleDocumentStack();
    $contract = createSaleDraftContract($admin, $room, $customer);
    $pdfFilename = expectedSalePdfFilename($contract);

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/sale-contract-drafts/{$contract->id}/approve")
        ->assertOk();

    $this->actingAs($admin, 'sanctum')
        ->get("/api/sale-contracts/approved/{$contract->id}/document/download")
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf')
        ->assertHeader('content-disposition', 'attachment; filename="'.$pdfFilename.'"');

    $this->actingAs($admin, 'sanctum')
        ->get("/api/sale-contracts/approved/{$contract->id}/document/export")
        ->assertOk()
        ->assertHeader('content-disposition', 'inline; filename="S-000001.html"');

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/sale-contracts/approved/{$contract->id}/document/email", [
            'email' => 'custom@example.com',
        ])
        ->assertOk()
        ->assertJsonPath('message', 'Sale contract document sent successfully.');

    Mail::assertSent(SaleContractDocumentMail::class, function (SaleContractDocumentMail $mail) {
        return $mail->hasTo('custom@example.com')
            && str_starts_with($mail->documentPdf, '%PDF');
    });
});

test('sale contract document email validates recipient email', function () {
    $admin = saleDocumentAdmin();
    ['room' => $room, 'customer' => $customer] = seedSaleDocumentStack();
    $contract = createSaleDraftContract($admin, $room, $customer);

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/sale-contract-drafts/{$contract->id}/document/email", [
            'email' => 'not-an-email',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

test('document filename helper builds TYPE-YEAR-MONTH-ROOM-NUMBER format', function () {
    expect(DocumentFilename::utility(new DateTimeImmutable('2027-07-01'), 'E-316'))
        ->toBe('UTL-2027-07-E-316.pdf');

    expect(DocumentFilename::invoice(new DateTimeImmutable('2027-07-15'), 'E-316', 'INV-000164'))
        ->toBe('INV-2027-07-E-316-000164.pdf');

    expect(DocumentFilename::receipt(new DateTimeImmutable('2027-07-20'), 'E-316', 'RCP-000154'))
        ->toBe('RCP-2027-07-E-316-000154.pdf');

    expect(DocumentFilename::rentContract(new DateTimeImmutable('2027-07-01'), 'E-316', 'R-000012'))
        ->toBe('R-2027-07-E-316-000012.pdf');

    expect(DocumentFilename::saleContract(new DateTimeImmutable('2027-07-01'), 'E-316', 'S-000008'))
        ->toBe('S-2027-07-E-316-000008.pdf');
});
