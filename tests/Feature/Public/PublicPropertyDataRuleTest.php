<?php

use App\Models\Building;
use App\Models\Room;
use App\Models\RoomImage;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('exposes only sourced public property fields and omits invented ones', function (): void {
    $building = Building::factory()->create([
        'building_name' => 'Rosewood Tower',
        'location' => 'Bahan Township, Yangon',
    ]);

    $room = Room::factory()->forRent()->create([
        'building_id' => $building->id,
        'room_number' => '12-A',
        'floor_number' => 12,
        'area_sqft' => 980,
        'width_ft' => 28,
        'length_ft' => 35,
        'rent_price' => 1500000,
        'rent_deposit_price' => 3000000,
        'description' => 'Corner unit with city views.',
        'status' => 'available',
        'type' => 'rent',
    ]);

    RoomImage::query()->create([
        'room_id' => $room->id,
        'image_path' => 'rooms/test.jpg',
        'is_primary' => true,
        'sort_order' => 1,
    ]);

    $response = $this->getJson('/api/public/properties?purpose=rent')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.id', $room->id)
        ->assertJsonPath('data.0.property_name', 'Rosewood Tower 12-A')
        ->assertJsonPath('data.0.township', 'Bahan')
        ->assertJsonPath('data.0.purpose', 'rent')
        ->assertJsonPath('data.0.monthly_rent', 1500000)
        ->assertJsonPath('data.0.area_sqft', 980)
        ->assertJsonPath('data.0.floor_number', 12);

    $payload = $response->json('data.0');

    expect($payload)->not->toHaveKeys([
        'bedrooms',
        'bathrooms',
        'amenities',
        'property_code',
        'property_type',
        'featured',
    ]);

    expect($payload)->toHaveKey('gallery_images');
    expect($payload['gallery_images'])->not->toBeEmpty();
});

it('returns inventory stats without a featured count', function (): void {
    Room::factory()->forRent()->create(['type' => 'rent', 'status' => 'available']);

    $this->getJson('/api/public/properties/stats')
        ->assertOk()
        ->assertJsonPath('data.total', 1)
        ->assertJsonPath('data.available', 1)
        ->assertJsonMissingPath('data.featured');
});
