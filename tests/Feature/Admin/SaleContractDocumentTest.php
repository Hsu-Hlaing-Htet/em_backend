<?php

use App\Mail\RentContractDocumentMail;
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

    $customer = User::query()->where('email', 'mgmg@gmail.com')->firstOrFail();

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

function expectedSaleDownloadFilename(Contract $contract): string
{
    return "Rosewood_Royale_Sale_Contract_{$contract->contract_number}.pdf";
}

function seedActiveRentDocumentStack(): array
{
    $building = Building::query()->create([
        'building_name' => 'Rosewood Tower',
        'location' => 'Yangon',
    ]);

    $room = Room::query()->create([
        'building_id' => $building->id,
        'room_number' => 'B-0701',
        'floor_number' => 7,
        'type' => 'rent',
        'status' => 'available',
        'area_sqft' => 900,
        'sale_price' => 0,
        'rent_price' => 365000,
        'rent_deposit_price' => 365000,
        'booking_deposit_price' => 0,
    ]);

    $customer = User::query()->where('email', 'mgmg@gmail.com')->firstOrFail();

    return compact('building', 'room', 'customer');
}

function createRentDraftContractForDocument(User $admin, Room $room, User $customer): Contract
{
    $response = test()->actingAs($admin, 'sanctum')
        ->postJson('/api/rent-contract-drafts', [
            'user_id' => $customer->id,
            'room_id' => $room->id,
            'payment_type' => 'installment',
            'duration_months' => 3,
            'start_date' => '2027-07-15',
        ])
        ->assertCreated();

    return Contract::query()->findOrFail($response->json('data.id'));
}

test('admin can download export and email draft sale contract document', function () {
    Mail::fake();

    $admin = saleDocumentAdmin();
    ['room' => $room, 'customer' => $customer] = seedSaleDocumentStack();
    $contract = createSaleDraftContract($admin, $room, $customer);
    $pdfFilename = expectedSaleDownloadFilename($contract);

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

    Mail::assertSent(SaleContractDocumentMail::class, function (SaleContractDocumentMail $mail) use ($customer) {
        return $mail->hasTo($customer->email)
            && $mail->emailSubject === 'Sale Contract Available'
            && $mail->attachments() === [];
    });
});

test('admin can download export and email approved sale contract document', function () {
    Mail::fake();

    $admin = saleDocumentAdmin();
    ['room' => $room, 'customer' => $customer] = seedSaleDocumentStack();
    $contract = createSaleDraftContract($admin, $room, $customer);
    $pdfFilename = expectedSaleDownloadFilename($contract);

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
            'html' => '<html><head><style>@media print { body { color: red; } }</style></head><body><article id="pdf-print" class="contract-doc-sheet">Sale preview</article></body></html>',
        ])
        ->assertOk()
        ->assertJsonPath('message', "Contract sent successfully to {$customer->email}.");

    Mail::assertSent(SaleContractDocumentMail::class, function (SaleContractDocumentMail $mail) use ($customer) {
        return $mail->hasTo($customer->email)
            && $mail->emailSubject === 'Sale Contract Available'
            && $mail->attachments() === []
            && ! str_contains($mail->render(), 'View in Customer Portal')
            && ! str_contains($mail->render(), 'href=')
            && ! str_contains($mail->render(), 'ngrok')
            && ! str_contains($mail->render(), 'localhost');
    });

    Mail::assertNotSent(SaleContractDocumentMail::class, fn (SaleContractDocumentMail $mail) => $mail->hasTo('custom@example.com'));
});

test('admin can email active rent contract document to the registered tenant', function () {
    Mail::fake();

    $admin = saleDocumentAdmin();
    ['room' => $room, 'customer' => $customer] = seedActiveRentDocumentStack();
    $contract = createRentDraftContractForDocument($admin, $room, $customer);

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/rent-contract-drafts/{$contract->id}/approve")
        ->assertOk();

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/rent-contracts/active/{$contract->id}/document/email", [
            'email' => 'custom@example.com',
            'html' => '<html><body><article id="pdf-print" class="contract-doc-sheet">Rent preview</article></body></html>',
        ])
        ->assertOk()
        ->assertJsonPath('message', "Contract sent successfully to {$customer->email}.");

    Mail::assertSent(RentContractDocumentMail::class, function (RentContractDocumentMail $mail) use ($customer) {
        return $mail->hasTo($customer->email)
            && $mail->emailSubject === 'Rent Contract Available'
            && $mail->attachments() === []
            && ! str_contains($mail->render(), 'View in Customer Portal')
            && ! str_contains($mail->render(), 'href=')
            && ! str_contains($mail->render(), 'ngrok')
            && ! str_contains($mail->render(), 'localhost');
    });

    Mail::assertNotSent(RentContractDocumentMail::class, fn (RentContractDocumentMail $mail) => $mail->hasTo('custom@example.com'));
});

test('unauthorized users cannot email active contract documents', function () {
    Mail::fake();

    $admin = saleDocumentAdmin();
    ['room' => $room, 'customer' => $customer] = seedSaleDocumentStack();
    $contract = createSaleDraftContract($admin, $room, $customer);

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/sale-contract-drafts/{$contract->id}/approve")
        ->assertOk();

    $this->actingAs($customer, 'sanctum')
        ->postJson("/api/sale-contracts/approved/{$contract->id}/document/email")
        ->assertForbidden();

    Mail::assertNothingSent();
});

test('active contract email endpoints do not send draft contracts', function () {
    Mail::fake();

    $admin = saleDocumentAdmin();
    ['room' => $saleRoom, 'customer' => $saleCustomer] = seedSaleDocumentStack();
    $saleContract = createSaleDraftContract($admin, $saleRoom, $saleCustomer);
    ['room' => $rentRoom, 'customer' => $rentCustomer] = seedActiveRentDocumentStack();
    $rentContract = createRentDraftContractForDocument($admin, $rentRoom, $rentCustomer);

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/sale-contracts/approved/{$saleContract->id}/document/email")
        ->assertNotFound();

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/rent-contracts/active/{$rentContract->id}/document/email")
        ->assertNotFound();

    Mail::assertNothingSent();
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
