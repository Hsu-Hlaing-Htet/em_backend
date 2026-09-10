<?php

use App\Models\Building;
use App\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('public users can list available properties', function () {
    $building = Building::query()->create([
        'building_name' => 'Rosewood Tower A',
        'location' => 'Bahan, Yangon',
        'status' => 'active',
    ]);

    Room::query()->create([
        'building_id' => $building->id,
        'room_number' => '101',
        'floor_number' => 1,
        'type' => 'rent',
        'status' => Room::STATUS_AVAILABLE,
        'rent_price' => 1200,
        'rent_deposit_price' => 2400,
        'area_sqft' => 850,
        'description' => 'Modern 1-bedroom rental with city views.',
    ]);

    Room::query()->create([
        'building_id' => $building->id,
        'room_number' => '102',
        'floor_number' => 1,
        'type' => 'rent',
        'status' => Room::STATUS_OCCUPIED,
        'rent_price' => 1500,
    ]);

    $response = $this->getJson('/api/public/properties')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'data' => [
                    '*' => [
                        'id',
                        'property_name',
                        'building_name',
                        'room_number',
                        'purpose',
                        'status',
                        'monthly_rent',
                        'rent_price',
                    ],
                ],
                'total',
            ],
        ]);

    expect($response->json('data.total'))->toBe(1)
        ->and($response->json('data.data.0.room_number'))->toBe('101');
});

test('public users can view single property details', function () {
    $building = Building::query()->create([
        'building_name' => 'Royal Heritage',
        'location' => 'Kamayut, Yangon',
        'status' => 'active',
    ]);

    $room = Room::query()->create([
        'building_id' => $building->id,
        'room_number' => '505',
        'floor_number' => 5,
        'type' => 'both',
        'status' => Room::STATUS_AVAILABLE,
        'rent_price' => 2000,
        'sale_price' => 350000,
        'rent_deposit_price' => 4000,
        'area_sqft' => 1400,
        'description' => 'Spacious executive penthouse.',
    ]);

    $this->getJson("/api/public/properties/{$room->id}")
        ->assertOk()
        ->assertJsonPath('data.room_number', '505')
        ->assertJsonPath('data.building_name', 'Royal Heritage')
        ->assertJsonPath('data.monthly_rent', 2000)
        ->assertJsonPath('data.sale_price', 350000);
});

test('public users can filter properties by purpose and price', function () {
    $building = Building::query()->create([
        'building_name' => 'City View',
        'location' => 'Sanchaung, Yangon',
        'status' => 'active',
    ]);

    Room::query()->create([
        'building_id' => $building->id,
        'room_number' => '201',
        'type' => 'rent',
        'status' => Room::STATUS_AVAILABLE,
        'rent_price' => 900,
    ]);

    Room::query()->create([
        'building_id' => $building->id,
        'room_number' => '202',
        'type' => 'rent',
        'status' => Room::STATUS_AVAILABLE,
        'rent_price' => 2500,
    ]);

    Room::query()->create([
        'building_id' => $building->id,
        'room_number' => '203',
        'type' => 'sale',
        'status' => Room::STATUS_AVAILABLE,
        'sale_price' => 150000,
    ]);

    $rentResponse = $this->getJson('/api/public/properties?purpose=rent&max_price=1000')
        ->assertOk();

    expect($rentResponse->json('data.total'))->toBe(1)
        ->and($rentResponse->json('data.data.0.room_number'))->toBe('201');
});
