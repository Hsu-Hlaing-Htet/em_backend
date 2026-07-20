<?php

use App\Mail\SaleContractDocumentMail;
use App\Models\Building;
use App\Models\Contract;
use App\Models\Room;
use App\Models\User;
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
            'start_date' => now()->toDateString(),
        ])
        ->assertCreated();

    return Contract::query()->findOrFail($response->json('data.id'));
}

test('admin can download export and email draft sale contract document', function () {
    Mail::fake();

    $admin = saleDocumentAdmin();
    ['room' => $room, 'customer' => $customer] = seedSaleDocumentStack();
    $contract = createSaleDraftContract($admin, $room, $customer);

    $this->actingAs($admin, 'sanctum')
        ->get("/api/sale-contract-drafts/{$contract->id}/document/download")
        ->assertOk()
        ->assertHeader('content-type', 'text/html; charset=UTF-8')
        ->assertHeader('content-disposition', 'attachment; filename="S-000001.html"');

    $this->actingAs($admin, 'sanctum')
        ->get("/api/sale-contract-drafts/{$contract->id}/document/export")
        ->assertOk()
        ->assertHeader('content-disposition', 'inline; filename="S-000001.html"');

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/sale-contract-drafts/{$contract->id}/document/email")
        ->assertOk()
        ->assertJsonPath('message', 'Sale contract document sent successfully.');

    Mail::assertSent(SaleContractDocumentMail::class, function (SaleContractDocumentMail $mail) use ($customer) {
        return $mail->hasTo($customer->email)
            && str_contains($mail->documentHtml, 'Property Sale Agreement')
            && str_contains($mail->documentHtml, 'S-000001');
    });
});

test('admin can download export and email approved sale contract document', function () {
    Mail::fake();

    $admin = saleDocumentAdmin();
    ['room' => $room, 'customer' => $customer] = seedSaleDocumentStack();
    $contract = createSaleDraftContract($admin, $room, $customer);

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/sale-contract-drafts/{$contract->id}/approve")
        ->assertOk();

    $this->actingAs($admin, 'sanctum')
        ->get("/api/sale-contracts/approved/{$contract->id}/document/download")
        ->assertOk()
        ->assertHeader('content-disposition', 'attachment; filename="S-000001.html"');

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
        return $mail->hasTo('custom@example.com');
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
