<?php

use App\Models\Building;
use App\Models\Role;
use App\Models\Room;
use App\Models\RoomImage;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function tb1PropertyAdmin(): User
{
    (new RoleSeeder)->run();
    (new UserSeeder)->run();

    return User::query()->where('email', 'admin@rosewoodroyale.com')->firstOrFail();
}

function tb1PropertyCustomer(): User
{
    return User::query()->where('email', 'mgmg@rosewoodroyale.com')->firstOrFail();
}

function tb1RoomPayload(int $buildingId, string $roomNumber = 'TB1-101'): array
{
    return [
        'building_id' => $buildingId,
        'room_number' => $roomNumber,
        'floor_number' => 1,
        'area_sqft' => 850,
        'width_ft' => 25,
        'length_ft' => 34,
        'type' => 'rent',
        'status' => 'available',
        'sale_price' => 0,
        'rent_price' => 500000,
        'rent_deposit_price' => 1000000,
        'booking_deposit_price' => 0,
        'description' => 'Timebox 1 test room',
    ];
}

test('admin can list create show and update buildings', function () {
    $admin = tb1PropertyAdmin();

    $create = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/buildings', [
            'building_name' => 'Timebox Tower',
            'location' => 'Yankin Township, Yangon',
            'description' => 'Primary Timebox 1 building',
        ])
        ->assertCreated()
        ->assertJsonPath('data.building_name', 'Timebox Tower')
        ->assertJsonPath('data.location', 'Yankin Township, Yangon')
        ->assertJsonStructure([
            'message',
            'data' => ['id', 'building_name', 'location', 'description', 'created_at', 'updated_at'],
        ]);

    $buildingId = $create->json('data.id');

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/buildings')
        ->assertOk()
        ->assertJsonPath('data.total', 1)
        ->assertJsonPath('data.data.0.id', $buildingId);

    $this->actingAs($admin, 'sanctum')
        ->getJson("/api/buildings/{$buildingId}")
        ->assertOk()
        ->assertJsonPath('data.building_name', 'Timebox Tower');

    $this->actingAs($admin, 'sanctum')
        ->putJson("/api/buildings/{$buildingId}", [
            'building_name' => 'Timebox Tower Updated',
            'location' => 'Bahan Township, Yangon',
            'description' => 'Updated',
        ])
        ->assertOk()
        ->assertJsonPath('data.building_name', 'Timebox Tower Updated')
        ->assertJsonPath('data.location', 'Bahan Township, Yangon');
});

test('building validation rejects missing required fields', function () {
    $admin = tb1PropertyAdmin();

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/buildings', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['building_name', 'location'], 'data');
});

test('admin can list create show and update rooms with building relationship', function () {
    $admin = tb1PropertyAdmin();

    $building = Building::query()->create([
        'building_name' => 'Room Relation Tower',
        'location' => 'Yangon',
    ]);

    $create = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/rooms', tb1RoomPayload($building->id))
        ->assertCreated()
        ->assertJsonPath('data.room_number', 'TB1-101')
        ->assertJsonPath('data.building_id', $building->id)
        ->assertJsonPath('data.building_name', 'Room Relation Tower')
        ->assertJsonPath('data.status', 'available')
        ->assertJsonPath('data.type', 'rent');

    $roomId = $create->json('data.id');

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/rooms')
        ->assertOk()
        ->assertJsonPath('data.total', 1);

    $this->actingAs($admin, 'sanctum')
        ->getJson("/api/rooms/{$roomId}")
        ->assertOk()
        ->assertJsonPath('data.id', $roomId)
        ->assertJsonPath('data.building_name', 'Room Relation Tower');

    $this->actingAs($admin, 'sanctum')
        ->putJson("/api/rooms/{$roomId}", array_merge(tb1RoomPayload($building->id, 'TB1-101B'), [
            'type' => 'both',
            'status' => 'maintenance',
            'sale_price' => 90000000,
            'booking_deposit_price' => 500000,
        ]))
        ->assertOk()
        ->assertJsonPath('data.room_number', 'TB1-101B')
        ->assertJsonPath('data.type', 'both')
        ->assertJsonPath('data.status', 'maintenance');

    expect(Room::query()->find($roomId)?->building_id)->toBe($building->id);
    expect($building->fresh()->rooms)->toHaveCount(1);
});

test('room validation rejects invalid building and status', function () {
    $admin = tb1PropertyAdmin();

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/rooms', [
            'building_id' => 999999,
            'room_number' => 'X-1',
            'floor_number' => 1,
            'area_sqft' => 100,
            'type' => 'invalid',
            'status' => 'invalid',
            'sale_price' => 0,
            'rent_price' => 0,
            'rent_deposit_price' => 0,
            'booking_deposit_price' => 0,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['building_id', 'type', 'status'], 'data');
});

test('admin can upload list and delete room images', function () {
    Storage::fake('public');
    $admin = tb1PropertyAdmin();

    $building = Building::query()->create([
        'building_name' => 'Image Tower',
        'location' => 'Yangon',
    ]);

    $room = Room::query()->create(tb1RoomPayload($building->id, 'IMG-201'));

    $upload = $this->actingAs($admin, 'sanctum')
        ->post('/api/room-images/upload', [
            'room_id' => $room->id,
            'image' => UploadedFile::fake()->image('living.jpg', 800, 600),
        ], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('message', 'Image uploaded successfully.')
        ->assertJsonStructure([
            'message',
            'data' => ['image_path', 'image_url'],
        ]);

    $imagePath = $upload->json('data.image_path');
    expect($imagePath)->toContain('buildings/');
    Storage::disk('public')->assertExists($imagePath);

    $store = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/room-images', [
            'room_id' => $room->id,
            'image_path' => $imagePath,
            'description' => 'Living room',
            'is_primary' => true,
            'sort_order' => 0,
        ])
        ->assertCreated()
        ->assertJsonPath('data.room_id', $room->id)
        ->assertJsonPath('data.is_primary', true);

    $roomImageId = $store->json('data.id');

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/room-images?room_id='.$room->id)
        ->assertOk()
        ->assertJsonPath('data.total', 1)
        ->assertJsonPath('data.data.0.id', $roomImageId);

    $this->actingAs($admin, 'sanctum')
        ->getJson("/api/rooms/{$room->id}")
        ->assertOk()
        ->assertJsonPath('data.primary_image_url', fn ($value) => is_string($value) && $value !== '');

    $this->actingAs($admin, 'sanctum')
        ->deleteJson("/api/room-images/{$roomImageId}")
        ->assertOk();

    expect(RoomImage::query()->find($roomImageId))->toBeNull();
});

test('invalid room image upload is rejected', function () {
    Storage::fake('public');
    $admin = tb1PropertyAdmin();

    $building = Building::query()->create([
        'building_name' => 'Invalid Image Tower',
        'location' => 'Yangon',
    ]);
    $room = Room::query()->create(tb1RoomPayload($building->id, 'IMG-BAD'));

    $this->actingAs($admin, 'sanctum')
        ->post('/api/room-images/upload', [
            'room_id' => $room->id,
            'image' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain'),
        ], ['Accept' => 'application/json'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['image'], 'data');

    $this->actingAs($admin, 'sanctum')
        ->post('/api/room-images/upload', [
            'room_id' => 999999,
            'image' => UploadedFile::fake()->image('ok.jpg'),
        ], ['Accept' => 'application/json'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['room_id'], 'data');
});

test('customer is forbidden from administrator property endpoints', function () {
    $admin = tb1PropertyAdmin();
    $customer = tb1PropertyCustomer();

    expect($customer->role->name)->toBe(Role::CUSTOMER);

    $building = Building::query()->create([
        'building_name' => 'Restricted Property',
        'location' => 'Yangon',
    ]);
    $room = Room::query()->create(tb1RoomPayload($building->id, 'REST-1'));

    $this->actingAs($customer, 'sanctum')->getJson('/api/buildings')->assertForbidden();
    $this->actingAs($customer, 'sanctum')->postJson('/api/buildings', [
        'building_name' => 'Nope',
        'location' => 'Yangon',
    ])->assertForbidden();
    $this->actingAs($customer, 'sanctum')->getJson('/api/rooms')->assertForbidden();
    $this->actingAs($customer, 'sanctum')->postJson('/api/rooms', tb1RoomPayload($building->id))->assertForbidden();
    $this->actingAs($customer, 'sanctum')->getJson('/api/room-images')->assertForbidden();

    $this->actingAs($admin, 'sanctum')->getJson('/api/buildings')->assertOk();
});

test('timebox 1 model relationships are wired', function () {
    tb1PropertyAdmin();

    $building = Building::query()->create([
        'building_name' => 'Relation Building',
        'location' => 'Yangon',
    ]);
    $room = Room::query()->create(tb1RoomPayload($building->id, 'REL-1'));
    $image = RoomImage::query()->create([
        'room_id' => $room->id,
        'image_path' => 'buildings/relation/REL-1/test.jpg',
        'description' => 'Primary',
        'is_primary' => true,
        'sort_order' => 0,
    ]);

    $user = User::query()->where('email', 'mgmg@rosewoodroyale.com')->firstOrFail();

    expect($building->rooms)->toHaveCount(1);
    expect($room->building->id)->toBe($building->id);
    expect($room->roomImages)->toHaveCount(1);
    expect($room->primaryRoomImage?->id)->toBe($image->id);
    expect($image->room->id)->toBe($room->id);
    expect($user->role)->not->toBeNull();
    expect($user->profile)->not->toBeNull();
    expect($user->profile->user_id)->toBe($user->id);
});

test('role and user seeders are idempotent', function () {
    (new RoleSeeder)->run();
    (new UserSeeder)->run();
    $roleCount = Role::query()->count();
    $userCount = User::query()->count();

    (new RoleSeeder)->run();
    (new UserSeeder)->run();

    expect(Role::query()->count())->toBe($roleCount);
    expect(User::query()->count())->toBe($userCount);
    expect(Role::query()->pluck('name')->sort()->values()->all())->toBe([
        Role::ADMIN,
        Role::CUSTOMER,
        Role::SUPER_ADMIN,
    ]);
});
