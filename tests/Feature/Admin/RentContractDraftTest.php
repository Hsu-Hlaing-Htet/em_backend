<?php

use App\Models\Building;
use App\Models\Contract;
use App\Models\Role;
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

    $customer = User::query()->where('email', 'mgmg@rosewoodroyale.com')->firstOrFail();

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
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.type', 'rent')
        ->assertJsonPath('data.contract_total', '1200000.00')
        ->assertJsonPath('data.deposit_amount', '2400000.00')
        ->assertJsonPath('data.room_price', '1200000.00');
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
