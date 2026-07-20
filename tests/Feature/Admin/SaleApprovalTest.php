<?php

use App\Models\Building;
use App\Models\Contract;
use App\Models\Room;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function saleAdminUser(): User
{
    (new RoleSeeder)->run();
    (new UserSeeder)->run();

    return User::query()->where('email', 'admin@rosewoodroyale.com')->firstOrFail();
}

function seedSaleProperty(): array
{
    $building = Building::query()->create([
        'building_name' => 'Rosewood Tower',
        'location' => 'Golden Valley Avenue, Yankin Township, Yangon',
    ]);

    $room = Room::query()->create([
        'building_id' => $building->id,
        'room_number' => 'S-801',
        'floor_number' => 8,
        'type' => 'sale',
        'status' => 'available',
        'area_sqft' => 1800,
        'sale_price' => 850000000,
        'rent_price' => 0,
        'rent_deposit_price' => 0,
        'booking_deposit_price' => 85000000,
    ]);

    $customer = User::query()->where('email', 'mgmg@rosewoodroyale.com')->firstOrFail();

    return compact('building', 'room', 'customer');
}

function createSaleDraftPayload(int $roomId, int $userId): array
{
    return [
        'user_id' => $userId,
        'room_id' => $roomId,
        'payment_type' => 'full',
        'start_date' => now()->addWeek()->toDateString(),
        'remark' => 'Customer requested full payment.',
    ];
}

test('admin can approve and reject sale contract drafts', function () {
    $admin = saleAdminUser();
    ['room' => $room, 'customer' => $customer] = seedSaleProperty();

    $createResponse = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/sale-contract-drafts', createSaleDraftPayload($room->id, $customer->id))
        ->assertCreated()
        ->assertJsonPath('data.status', 'draft');

    $contractId = $createResponse->json('data.id');

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/sale-contract-drafts')
        ->assertOk()
        ->assertJsonPath('data.total', 1);

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/sale-contract-drafts/{$contractId}/approve")
        ->assertOk()
        ->assertJsonPath('data.status', 'approved');

    expect(Room::query()->find($room->id)?->status)->toBe('reserved');

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/sale-contracts/approved')
        ->assertOk()
        ->assertJsonPath('data.total', 1)
        ->assertJsonPath('data.data.0.id', $contractId);

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/sale-contract-drafts')
        ->assertOk()
        ->assertJsonPath('data.total', 0);
});

test('admin can reject sale contract draft', function () {
    $admin = saleAdminUser();
    ['room' => $room, 'customer' => $customer] = seedSaleProperty();

    $contractId = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/sale-contract-drafts', createSaleDraftPayload($room->id, $customer->id))
        ->assertCreated()
        ->json('data.id');

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/sale-contract-drafts/{$contractId}/reject", [
            'rejection_reason' => 'Incomplete buyer documentation.',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'rejected')
        ->assertJsonPath('data.remark', 'Incomplete buyer documentation.');

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/sale-contracts/approved')
        ->assertOk()
        ->assertJsonPath('data.total', 0);
});

test('public sale listing returns only approved sale contracts', function () {
    $admin = saleAdminUser();
    ['room' => $room, 'customer' => $customer] = seedSaleProperty();

    $this->getJson('/api/public/properties?purpose=sale')
        ->assertOk()
        ->assertJsonPath('meta.total', 0);

    $contractId = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/sale-contract-drafts', createSaleDraftPayload($room->id, $customer->id))
        ->json('data.id');

    $this->getJson('/api/public/properties?purpose=sale')
        ->assertOk()
        ->assertJsonPath('meta.total', 0);

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/sale-contract-drafts/{$contractId}/approve")
        ->assertOk();

    $this->getJson('/api/public/properties?purpose=sale')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.id', $room->id);

    expect(Contract::query()->find($contractId)?->status)->toBe('approved');
});
