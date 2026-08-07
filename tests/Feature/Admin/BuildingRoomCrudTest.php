<?php

use App\Models\Building;
use App\Models\Room;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function propertyAdmin(): User
{
    (new RoleSeeder)->run();
    (new UserSeeder)->run();

    return User::query()->where('email', 'admin@rosewoodroyale.com')->firstOrFail();
}

test('admin can create update and delete buildings', function () {
    $admin = propertyAdmin();

    $createResponse = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/buildings', [
            'building_name' => 'Golden Pagoda Residences',
            'location' => 'Tamwe Township, Yangon',
            'description' => 'Premium tower near Shwedagon Pagoda.',
        ])
        ->assertCreated()
        ->assertJsonPath('data.building_name', 'Golden Pagoda Residences');

    $buildingId = $createResponse->json('data.id');

    $this->actingAs($admin, 'sanctum')
        ->putJson("/api/buildings/{$buildingId}", [
            'building_name' => 'Golden Pagoda Residences Tower A',
            'location' => 'Tamwe Township, Yangon',
            'description' => 'Updated description.',
        ])
        ->assertOk()
        ->assertJsonPath('data.building_name', 'Golden Pagoda Residences Tower A');

    $this->actingAs($admin, 'sanctum')
        ->deleteJson("/api/buildings/{$buildingId}")
        ->assertOk();

    expect(Building::query()->find($buildingId))->toBeNull();
});

test('admin can create update and delete rooms with building relationship', function () {
    $admin = propertyAdmin();

    $building = Building::query()->create([
        'building_name' => 'Inya Lake View',
        'location' => 'Bahan Township, Yangon',
    ]);

    $createResponse = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/rooms', [
            'building_id' => $building->id,
            'room_number' => 'ILV-1201',
            'floor_number' => 12,
            'area_sqft' => 980,
            'type' => 'rent',
            'status' => 'available',
            'sale_price' => 0,
            'rent_price' => 650000,
            'rent_deposit_price' => 1300000,
            'booking_deposit_price' => 0,
            'description' => 'Lake-facing rental unit.',
        ])
        ->assertCreated()
        ->assertJsonPath('data.room_number', 'ILV-1201')
        ->assertJsonPath('data.building_name', 'Inya Lake View');

    $roomId = $createResponse->json('data.id');

    $this->actingAs($admin, 'sanctum')
        ->putJson("/api/rooms/{$roomId}", [
            'building_id' => $building->id,
            'room_number' => 'ILV-1201A',
            'floor_number' => 12,
            'area_sqft' => 980,
            'type' => 'both',
            'status' => 'available',
            'sale_price' => 85000000,
            'rent_price' => 650000,
            'rent_deposit_price' => 1300000,
            'booking_deposit_price' => 500000,
            'description' => 'Updated lake-facing unit.',
        ])
        ->assertOk()
        ->assertJsonPath('data.room_number', 'ILV-1201A');

    $this->actingAs($admin, 'sanctum')
        ->deleteJson("/api/rooms/{$roomId}")
        ->assertOk();

    expect(Room::query()->find($roomId))->toBeNull();
});

test('customer cannot manage buildings or rooms', function () {
    $admin = propertyAdmin();
    $customer = User::query()->where('email', 'mgmg@rosewoodroyale.com')->firstOrFail();

    $building = Building::query()->create([
        'building_name' => 'Restricted Tower',
        'location' => 'Yangon',
    ]);

    $this->actingAs($customer, 'sanctum')
        ->getJson('/api/buildings')
        ->assertForbidden();

    $this->actingAs($customer, 'sanctum')
        ->postJson('/api/buildings', [
            'building_name' => 'Customer Tower',
            'location' => 'Yangon',
        ])
        ->assertForbidden();

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/buildings')
        ->assertOk();
});
