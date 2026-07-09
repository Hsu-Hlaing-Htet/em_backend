<?php

use App\Models\Building;
use App\Models\ChargeType;
use App\Models\Contract;
use App\Models\Role;
use App\Models\Room;
use App\Models\User;
use App\Models\Utility;
use App\Models\UtilityItem;
use App\Models\UtilityRate;
use App\Models\UtilityType;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function adminUser(): User
{
    (new RoleSeeder)->run();
    (new UserSeeder)->run();

    return User::query()->where('email', 'admin@rosewoodroyale.com')->firstOrFail();
}

function seedPropertyStack(): array
{
    $building = Building::query()->create([
        'building_name' => 'Rosewood Tower',
        'location' => 'Yangon',
    ]);

    $room = Room::query()->create([
        'building_id' => $building->id,
        'room_number' => '8A',
        'floor_number' => 8,
        'type' => 'rent',
        'status' => 'available',
        'area_sqft' => 1200,
        'sale_price' => 0,
        'rent_price' => 500000,
        'rent_deposit_price' => 50000,
        'booking_deposit_price' => 10000,
    ]);

    $customer = User::query()->where('email', 'mgmg@rosewoodroyale.com')->firstOrFail();

    return compact('building', 'room', 'customer');
}

test('api returns json 404 for unknown routes', function () {
    $this->getJson('/api/unknown-resource')
        ->assertNotFound()
        ->assertJson([
            'message' => 'Resource not found.',
            'status' => 404,
        ]);
});

test('admin can create and list buildings', function () {
    $admin = adminUser();

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/buildings', [
            'building_name' => 'Lake View',
            'location' => 'Mandalay',
            'description' => 'Premium residences',
        ])
        ->assertCreated()
        ->assertJsonPath('data.building_name', 'Lake View');

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/buildings')
        ->assertOk()
        ->assertJsonPath('data.total', 1);
});

test('contract approval workflow updates room status', function () {
    $admin = adminUser();
    ['room' => $room, 'customer' => $customer] = seedPropertyStack();

    $create = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/contracts', [
            'user_id' => $customer->id,
            'room_id' => $room->id,
            'type' => 'rent',
            'payment_type' => 'installment',
            'duration_months' => 12,
            'contract_total' => 6000000,
            'start_date' => now()->toDateString(),
            'billing_day' => 1,
        ])
        ->assertCreated();

    $contractId = $create->json('data.id');

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/contracts/{$contractId}/submit")
        ->assertOk()
        ->assertJsonPath('data.status', 'pending');

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/contracts/{$contractId}/approve")
        ->assertOk()
        ->assertJsonPath('data.status', 'active');

    expect(Room::query()->find($room->id)?->status)->toBe('occupied');
});

test('utility approval merges charges into draft invoice', function () {
    $admin = adminUser();
    ['room' => $room, 'customer' => $customer] = seedPropertyStack();

    ChargeType::query()->create(['name' => 'Utility', 'slug' => 'utility', 'status' => 'active']);

    $utilityType = UtilityType::query()->create(['name' => 'Electricity', 'slug' => 'electricity', 'status' => 'active']);
    UtilityRate::query()->create([
        'utility_type_id' => $utilityType->id,
        'unit_price' => 150,
        'status' => 'active',
        'effective_date' => now()->subMonth()->toDateString(),
    ]);

    $contract = Contract::query()->create([
        'contract_number' => 'CTR-TEST-0001',
        'user_id' => $customer->id,
        'room_id' => $room->id,
        'contract_total' => 6000000,
        'type' => 'rent',
        'payment_type' => 'installment',
        'duration_months' => 12,
        'billing_day' => 1,
        'status' => 'active',
        'created_by' => $admin->id,
    ]);

    $room->update(['status' => 'occupied']);

    $utility = Utility::query()->create([
        'room_id' => $room->id,
        'billing_month' => now()->startOfMonth()->toDateString(),
        'total_amount' => 15000,
        'status' => 'draft',
        'created_by' => $admin->id,
    ]);

    UtilityItem::query()->create([
        'utility_id' => $utility->id,
        'utility_type_id' => $utilityType->id,
        'previous_reading' => 100,
        'current_reading' => 200,
        'usage' => 100,
        'unit_price' => 150,
        'amount' => 15000,
    ]);

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/utilities/{$utility->id}/submit")
        ->assertOk();

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/utilities/{$utility->id}/approve")
        ->assertOk()
        ->assertJsonPath('data.status', 'approved');

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/invoices')
        ->assertOk()
        ->assertJsonPath('data.total', 1);
});

test('dashboard endpoint returns aggregate stats', function () {
    $admin = adminUser();
    seedPropertyStack();

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/dashboard')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'totals' => ['rooms', 'residents', 'contracts', 'invoices'],
                'room_status',
                'invoice_status',
                'revenue',
            ],
        ]);
});
