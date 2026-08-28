<?php

use App\Models\Building;
use App\Models\Room;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function rentDraftAdmin(): User
{
    (new RoleSeeder)->run();
    (new UserSeeder)->run();

    return User::query()->where('email', 'admin@rosewoodroyale.com')->firstOrFail();
}

function seedRentDraftStack(): array
{
    $building = Building::query()->create([
        'building_name' => 'Rosewood Residences',
        'location' => 'Yangon',
    ]);

    $room = Room::query()->create([
        'building_id' => $building->id,
        'room_number' => 'B-801',
        'floor_number' => 8,
        'type' => 'rent',
        'status' => 'available',
        'area_sqft' => 900,
        'sale_price' => 0,
        'rent_price' => 1200000,
        'rent_deposit_price' => 2400000,
        'booking_deposit_price' => 0,
    ]);

    $customer = User::query()->where('email', 'mgmg@gmail.com')->firstOrFail();

    return compact('building', 'room', 'customer');
}

test('admin can create rent contract draft with auto generated number and defaults', function () {
    $admin = rentDraftAdmin();
    ['room' => $room, 'customer' => $customer] = seedRentDraftStack();

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/rent-contract-drafts', [
            'user_id' => $customer->id,
            'room_id' => $room->id,
            'payment_type' => 'full',
            'start_date' => now()->toDateString(),
        ])
        ->assertCreated()
        ->assertJsonPath('data.contract_number', 'R-000001')
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.type', 'rent')
        ->assertJsonPath('data.contract_total', '1200000.00')
        ->assertJsonPath('data.deposit_amount', '2400000.00')
        ->assertJsonPath('data.room_price', '1200000.00');
});

test('admin can create rent installment draft with total from monthly rent and duration', function () {
    $admin = rentDraftAdmin();
    ['room' => $room, 'customer' => $customer] = seedRentDraftStack();

    $room->update([
        'rent_price' => 365000,
        'rent_deposit_price' => 500000,
    ]);

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/rent-contract-drafts', [
            'user_id' => $customer->id,
            'room_id' => $room->id,
            'payment_type' => 'installment',
            'duration_months' => 3,
            'contract_total' => 999999,
            'start_date' => now()->toDateString(),
        ])
        ->assertCreated()
        ->assertJsonPath('data.contract_total', '1095000.00')
        ->assertJsonPath('data.deposit_amount', '500000.00')
        ->assertJsonPath('data.remaining_balance', '1095000.00')
        ->assertJsonPath('data.estimated_monthly_payment', '365000.00')
        ->assertJsonPath('data.room_price', '365000.00');
});

test('admin can update rent draft duration and recalculate total', function () {
    $admin = rentDraftAdmin();
    ['room' => $room, 'customer' => $customer] = seedRentDraftStack();

    $room->update([
        'rent_price' => 365000,
        'rent_deposit_price' => 500000,
    ]);

    $draftId = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/rent-contract-drafts', [
            'user_id' => $customer->id,
            'room_id' => $room->id,
            'payment_type' => 'installment',
            'duration_months' => 3,
            'start_date' => now()->toDateString(),
        ])
        ->assertCreated()
        ->json('data.id');

    $this->actingAs($admin, 'sanctum')
        ->putJson("/api/rent-contract-drafts/{$draftId}", [
            'duration_months' => 6,
            'contract_total' => 1,
        ])
        ->assertOk()
        ->assertJsonPath('data.contract_total', '2190000.00')
        ->assertJsonPath('data.remaining_balance', '2190000.00')
        ->assertJsonPath('data.estimated_monthly_payment', '365000.00');
});

test('admin can approve rent contract draft and list active contracts', function () {
    $admin = rentDraftAdmin();
    ['room' => $room, 'customer' => $customer] = seedRentDraftStack();

    $draftId = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/rent-contract-drafts', [
            'user_id' => $customer->id,
            'room_id' => $room->id,
            'payment_type' => 'full',
            'start_date' => now()->toDateString(),
        ])
        ->assertCreated()
        ->json('data.id');

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/rent-contract-drafts/{$draftId}/approve")
        ->assertOk()
        ->assertJsonPath('data.status', 'active');

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/rent-contracts/active')
        ->assertOk()
        ->assertJsonPath('data.data.0.contract_number', 'R-000001')
        ->assertJsonPath('data.total', 1);

    expect($room->fresh()->status)->toBe('occupied');
});
