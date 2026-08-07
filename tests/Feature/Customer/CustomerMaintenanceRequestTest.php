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

function maintenanceUsers(): array
{
    (new RoleSeeder)->run();
    (new UserSeeder)->run();

    return [
        'admin' => User::query()->where('email', 'admin@rosewoodroyale.com')->firstOrFail(),
        'customer' => User::query()->where('email', 'mgmg@rosewoodroyale.com')->firstOrFail(),
        'otherCustomer' => User::query()->where('email', 'hlahla@rosewoodroyale.com')->firstOrFail(),
    ];
}

function createOccupiedRoomFor(User $customer, User $admin, string $status = 'active'): Room
{
    $building = Building::query()->create([
        'building_name' => 'Maintenance Tower',
        'location' => 'Yangon',
    ]);

    $room = Room::query()->create([
        'building_id' => $building->id,
        'room_number' => 'M-'.fake()->unique()->numberBetween(100, 999),
        'floor_number' => 3,
        'type' => 'rent',
        'status' => 'occupied',
        'area_sqft' => 900,
        'sale_price' => 0,
        'rent_price' => 400000,
        'rent_deposit_price' => 40000,
        'booking_deposit_price' => 10000,
    ]);

    Contract::query()->create([
        'contract_number' => 'R-MNT-'.fake()->unique()->numerify('######'),
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

it('lets a customer create a maintenance request for an assigned active-contract room', function (): void {
    ['admin' => $admin, 'customer' => $customer] = maintenanceUsers();
    $room = createOccupiedRoomFor($customer, $admin);

    $response = $this->actingAs($customer, 'sanctum')
        ->postJson('/api/customer/maintenance-requests', [
            'room_id' => $room->id,
            'title' => 'Leaking bathroom pipe',
            'category' => 'plumbing',
            'priority' => 'high',
            'description' => 'Water is leaking under the sink.',
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.user_id', $customer->id)
        ->assertJsonPath('data.room_id', $room->id)
        ->assertJsonPath('data.category', 'plumbing')
        ->assertJsonPath('data.priority', 'high');

    $this->assertDatabaseHas('maintenance_requests', [
        'id' => $response->json('data.id'),
        'user_id' => $customer->id,
        'room_id' => $room->id,
        'status' => 'pending',
        'created_by' => $customer->id,
    ]);
});

it('rejects rooms that are not linked to an active approved customer contract', function (): void {
    ['admin' => $admin, 'customer' => $customer, 'otherCustomer' => $otherCustomer] = maintenanceUsers();
    $ownDraftRoom = createOccupiedRoomFor($customer, $admin, 'draft');
    $otherRoom = createOccupiedRoomFor($otherCustomer, $admin, 'active');

    $this->actingAs($customer, 'sanctum')
        ->postJson('/api/customer/maintenance-requests', [
            'room_id' => $ownDraftRoom->id,
            'title' => 'Invalid room request',
            'category' => 'general',
            'priority' => 'low',
            'description' => 'Should not be accepted.',
        ])
        ->assertStatus(422);

    $this->actingAs($customer, 'sanctum')
        ->postJson('/api/customer/maintenance-requests', [
            'room_id' => $otherRoom->id,
            'title' => 'Someone else room',
            'category' => 'general',
            'priority' => 'low',
            'description' => 'Should not be accepted.',
        ])
        ->assertStatus(422);
});

it('lists and shows only the authenticated customer maintenance requests', function (): void {
    ['admin' => $admin, 'customer' => $customer, 'otherCustomer' => $otherCustomer] = maintenanceUsers();
    $ownRoom = createOccupiedRoomFor($customer, $admin);
    $otherRoom = createOccupiedRoomFor($otherCustomer, $admin);

    $ownRequest = MaintenanceRequest::query()->create([
        'room_id' => $ownRoom->id,
        'user_id' => $customer->id,
        'created_by' => $customer->id,
        'title' => 'Own request',
        'category' => 'electrical',
        'priority' => 'medium',
        'description' => 'Outlet not working',
        'status' => 'pending',
    ]);

    $otherRequest = MaintenanceRequest::query()->create([
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
        ->assertJsonPath('data.data.0.id', $ownRequest->id);

    $this->actingAs($customer, 'sanctum')
        ->getJson("/api/customer/maintenance-requests/{$ownRequest->id}")
        ->assertOk()
        ->assertJsonPath('data.title', 'Own request');

    $this->actingAs($customer, 'sanctum')
        ->getJson("/api/customer/maintenance-requests/{$otherRequest->id}")
        ->assertNotFound();
});

it('forbids non-customers from accessing customer maintenance endpoints', function (): void {
    ['admin' => $admin, 'customer' => $customer] = maintenanceUsers();
    $room = createOccupiedRoomFor($customer, $admin);

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/customer/maintenance-requests')
        ->assertForbidden();

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/customer/maintenance-requests', [
            'room_id' => $room->id,
            'title' => 'Admin attempt',
            'category' => 'general',
            'priority' => 'low',
            'description' => 'Should be forbidden.',
        ])
        ->assertForbidden();
});

it('lets admin see and update a customer-submitted maintenance request', function (): void {
    ['admin' => $admin, 'customer' => $customer] = maintenanceUsers();
    $room = createOccupiedRoomFor($customer, $admin);

    $createResponse = $this->actingAs($customer, 'sanctum')
        ->postJson('/api/customer/maintenance-requests', [
            'room_id' => $room->id,
            'title' => 'Broken door hinge',
            'category' => 'general',
            'priority' => 'medium',
            'description' => 'Door does not close properly.',
        ])
        ->assertCreated();

    $requestId = $createResponse->json('data.id');

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/maintenance-requests')
        ->assertOk()
        ->assertJsonFragment(['id' => $requestId, 'status' => 'pending']);

    $this->actingAs($admin, 'sanctum')
        ->getJson("/api/maintenance-requests/{$requestId}")
        ->assertOk()
        ->assertJsonPath('data.title', 'Broken door hinge');

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/maintenance-requests/{$requestId}/start")
        ->assertOk()
        ->assertJsonPath('data.status', 'in_progress');

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/maintenance-requests/{$requestId}/complete", [
            'resolution_note' => 'Hinge replaced and door aligned.',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.resolution_note', 'Hinge replaced and door aligned.');

    $this->actingAs($customer, 'sanctum')
        ->getJson("/api/customer/maintenance-requests/{$requestId}")
        ->assertOk()
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.resolution_note', 'Hinge replaced and door aligned.');
});

it('lets customer see rejection reason after admin rejects the request', function (): void {
    ['admin' => $admin, 'customer' => $customer] = maintenanceUsers();
    $room = createOccupiedRoomFor($customer, $admin);

    $requestId = $this->actingAs($customer, 'sanctum')
        ->postJson('/api/customer/maintenance-requests', [
            'room_id' => $room->id,
            'title' => 'Cosmetic wall scratch',
            'category' => 'general',
            'priority' => 'low',
            'description' => 'Minor scratch near entry.',
        ])
        ->assertCreated()
        ->json('data.id');

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/maintenance-requests/{$requestId}/reject", [
            'rejection_reason' => 'Outside maintenance scope. Please contact building management office.',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'rejected');

    $this->actingAs($customer, 'sanctum')
        ->getJson("/api/customer/maintenance-requests/{$requestId}")
        ->assertOk()
        ->assertJsonPath('data.status', 'rejected')
        ->assertJsonPath(
            'data.rejection_reason',
            'Outside maintenance scope. Please contact building management office.',
        );
});

it('returns eligible rooms from active approved contracts only', function (): void {
    ['admin' => $admin, 'customer' => $customer] = maintenanceUsers();
    $activeRoom = createOccupiedRoomFor($customer, $admin, 'active');
    createOccupiedRoomFor($customer, $admin, 'completed');

    $this->actingAs($customer, 'sanctum')
        ->getJson('/api/customer/maintenance-rooms')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $activeRoom->id);
});
