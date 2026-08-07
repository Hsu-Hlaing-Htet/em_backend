<?php

use App\Models\Building;
use App\Models\Contract;
use App\Models\MaintenanceRequest;
use App\Models\Room;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function tb5Users(): array
{
    (new RoleSeeder)->run();
    (new UserSeeder)->run();

    return [
        'admin' => User::query()->where('email', 'admin@rosewoodroyale.com')->firstOrFail(),
        'customer' => User::query()->where('email', 'mgmg@rosewoodroyale.com')->firstOrFail(),
        'otherCustomer' => User::query()->where('email', 'hlahla@rosewoodroyale.com')->firstOrFail(),
    ];
}

function tb5OccupiedRoom(User $customer, User $admin, string $status = 'active'): Room
{
    $building = Building::query()->create([
        'building_name' => 'TB5 Maintenance Tower',
        'location' => 'Yangon',
    ]);

    $room = Room::query()->create([
        'building_id' => $building->id,
        'room_number' => 'TB5-'.fake()->unique()->numberBetween(100, 999),
        'floor_number' => 5,
        'type' => 'rent',
        'status' => 'occupied',
        'area_sqft' => 900,
        'sale_price' => 0,
        'rent_price' => 400000,
        'rent_deposit_price' => 40000,
        'booking_deposit_price' => 10000,
    ]);

    Contract::query()->create([
        'contract_number' => 'R-TB5-'.fake()->unique()->numerify('######'),
        'user_id' => $customer->id,
        'room_id' => $room->id,
        'contract_total' => 4800000,
        'deposit_amount' => 40000,
        'type' => 'rent',
        'payment_type' => 'full',
        'duration_months' => 12,
        'billing_day' => 5,
        'status' => $status,
        'created_by' => $admin->id,
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonths(11)->toDateString(),
    ]);

    return $room;
}

test('customer can create maintenance request for active contract room only', function () {
    ['admin' => $admin, 'customer' => $customer, 'otherCustomer' => $otherCustomer] = tb5Users();
    $ownRoom = tb5OccupiedRoom($customer, $admin, 'active');
    $draftRoom = tb5OccupiedRoom($customer, $admin, 'draft');
    $otherRoom = tb5OccupiedRoom($otherCustomer, $admin, 'active');

    $this->actingAs($customer, 'sanctum')
        ->postJson('/api/customer/maintenance-requests', [
            'room_id' => $ownRoom->id,
            'title' => 'Leaking faucet',
            'category' => 'plumbing',
            'priority' => 'high',
            'description' => 'Kitchen faucet drips continuously.',
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.user_id', $customer->id)
        ->assertJsonPath('data.room_id', $ownRoom->id);

    $this->actingAs($customer, 'sanctum')
        ->postJson('/api/customer/maintenance-requests', [
            'room_id' => $draftRoom->id,
            'title' => 'Invalid draft room',
            'category' => 'general',
            'priority' => 'low',
            'description' => 'Should be rejected.',
        ])
        ->assertStatus(422);

    $this->actingAs($customer, 'sanctum')
        ->postJson('/api/customer/maintenance-requests', [
            'room_id' => $otherRoom->id,
            'title' => 'Other room',
            'category' => 'general',
            'priority' => 'low',
            'description' => 'Should be rejected.',
        ])
        ->assertStatus(422);
});

test('customer views only own maintenance requests', function () {
    ['admin' => $admin, 'customer' => $customer, 'otherCustomer' => $otherCustomer] = tb5Users();
    $ownRoom = tb5OccupiedRoom($customer, $admin);
    $otherRoom = tb5OccupiedRoom($otherCustomer, $admin);

    $own = MaintenanceRequest::query()->create([
        'room_id' => $ownRoom->id,
        'user_id' => $customer->id,
        'created_by' => $customer->id,
        'title' => 'Own request',
        'category' => 'electrical',
        'priority' => 'medium',
        'description' => 'Outlet issue',
        'status' => 'pending',
    ]);

    $other = MaintenanceRequest::query()->create([
        'room_id' => $otherRoom->id,
        'user_id' => $otherCustomer->id,
        'created_by' => $otherCustomer->id,
        'title' => 'Other request',
        'category' => 'hvac',
        'priority' => 'high',
        'description' => 'AC issue',
        'status' => 'pending',
    ]);

    $this->actingAs($customer, 'sanctum')
        ->getJson('/api/customer/maintenance-requests')
        ->assertOk()
        ->assertJsonPath('data.total', 1)
        ->assertJsonPath('data.data.0.id', $own->id);

    $this->actingAs($customer, 'sanctum')
        ->getJson("/api/customer/maintenance-requests/{$own->id}")
        ->assertOk();

    $this->actingAs($customer, 'sanctum')
        ->getJson("/api/customer/maintenance-requests/{$other->id}")
        ->assertNotFound();
});

test('administrator can view start complete and reject maintenance requests', function () {
    ['admin' => $admin, 'customer' => $customer] = tb5Users();
    $room = tb5OccupiedRoom($customer, $admin);

    $requestId = $this->actingAs($customer, 'sanctum')
        ->postJson('/api/customer/maintenance-requests', [
            'room_id' => $room->id,
            'title' => 'Broken hinge',
            'category' => 'general',
            'priority' => 'medium',
            'description' => 'Door hinge broken.',
        ])
        ->assertCreated()
        ->json('data.id');

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/maintenance-requests')
        ->assertOk()
        ->assertJsonFragment(['id' => $requestId, 'status' => 'pending']);

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/maintenance-requests/{$requestId}/start")
        ->assertOk()
        ->assertJsonPath('data.status', 'in_progress');

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/maintenance-requests/{$requestId}/complete", [
            'resolution_note' => 'Hinge replaced.',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.resolution_note', 'Hinge replaced.');

    $rejectId = $this->actingAs($customer, 'sanctum')
        ->postJson('/api/customer/maintenance-requests', [
            'room_id' => $room->id,
            'title' => 'Cosmetic scratch',
            'category' => 'general',
            'priority' => 'low',
            'description' => 'Wall scratch.',
        ])
        ->assertCreated()
        ->json('data.id');

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/maintenance-requests/{$rejectId}/reject", [
            'rejection_reason' => 'Outside maintenance scope.',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'rejected');
});

test('customer cannot access admin maintenance endpoints and admin cannot use customer portal', function () {
    ['admin' => $admin, 'customer' => $customer] = tb5Users();
    $room = tb5OccupiedRoom($customer, $admin);

    $this->actingAs($customer, 'sanctum')
        ->getJson('/api/maintenance-requests')
        ->assertForbidden();

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/customer/maintenance-requests')
        ->assertForbidden();

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/customer/maintenance-requests', [
            'room_id' => $room->id,
            'title' => 'Admin attempt',
            'category' => 'general',
            'priority' => 'low',
            'description' => 'Forbidden.',
        ])
        ->assertForbidden();
});

test('dashboard summary endpoints enforce role access', function () {
    ['admin' => $admin, 'customer' => $customer] = tb5Users();

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/admin/dashboard/charts')
        ->assertOk()
        ->assertJsonStructure([
            'kpi_stats',
            'revenue_summary',
            'revenue_chart',
            'property_stats',
            'invoice_stats',
        ]);

    $this->actingAs($customer, 'sanctum')
        ->getJson('/api/admin/dashboard/charts')
        ->assertForbidden();

    $this->actingAs($customer, 'sanctum')
        ->getJson('/api/customer/dashboard')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'active_contracts',
                'completed_contracts',
                'unpaid_invoices',
                'paid_invoices',
                'total_payments',
                'pending_payments',
                'completed_payments',
                'total_paid_amount',
                'recent_payments',
            ],
        ]);

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/customer/dashboard')
        ->assertForbidden();
});

test('timebox 5 model relationships and role seeder idempotency', function () {
    ['admin' => $admin, 'customer' => $customer] = tb5Users();
    $room = tb5OccupiedRoom($customer, $admin);

    $request = MaintenanceRequest::query()->create([
        'room_id' => $room->id,
        'user_id' => $customer->id,
        'created_by' => $customer->id,
        'title' => 'Relation check',
        'category' => 'appliance',
        'priority' => 'low',
        'description' => 'Fridge noise',
        'status' => 'pending',
    ]);

    expect($request->room->id)->toBe($room->id);
    expect($request->user->id)->toBe($customer->id);
    expect($request->creator->id)->toBe($customer->id);

    $roleCount = \App\Models\Role::query()->count();
    $userCount = User::query()->count();
    (new RoleSeeder)->run();
    (new UserSeeder)->run();
    expect(\App\Models\Role::query()->count())->toBe($roleCount);
    expect(User::query()->count())->toBe($userCount);
});
