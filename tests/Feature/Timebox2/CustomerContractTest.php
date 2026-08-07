<?php

use App\Models\Building;
use App\Models\Contract;
use App\Models\PaymentPlan;
use App\Models\Role;
use App\Models\Room;
use App\Models\User;
use Database\Seeders\PaymentPlanSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function tb2Admin(): User
{
    (new RoleSeeder)->run();
    (new UserSeeder)->run();
    (new PaymentPlanSeeder)->run();

    return User::query()->where('email', 'admin@rosewoodroyale.com')->firstOrFail();
}

function tb2Customer(): User
{
    return User::query()->where('email', 'mgmg@rosewoodroyale.com')->firstOrFail();
}

function tb2OtherCustomer(): User
{
    return User::query()->where('email', 'hlahla@rosewoodroyale.com')->firstOrFail();
}

function tb2ResidentPayload(string $email): array
{
    return [
        'name' => 'TB2 Customer',
        'email' => $email,
        'password' => 'password123',
        'phone' => '09-11112222',
        'nrc' => '12/ABC(N)123456',
        'dob' => '1990-01-15',
        'gender' => 'male',
        'address' => 'Yankin, Yangon',
    ];
}

function tb2CreateRoom(string $type = 'rent', string $roomNumber = 'TB2-801'): Room
{
    $building = Building::query()->create([
        'building_name' => 'TB2 Contract Tower',
        'location' => 'Yangon',
    ]);

    return Room::query()->create([
        'building_id' => $building->id,
        'room_number' => $roomNumber,
        'floor_number' => 8,
        'type' => $type,
        'status' => 'available',
        'area_sqft' => 1000,
        'sale_price' => $type === 'rent' ? 0 : 850000000,
        'rent_price' => $type === 'sale' ? 0 : 1200000,
        'rent_deposit_price' => $type === 'sale' ? 0 : 2400000,
        'booking_deposit_price' => $type === 'rent' ? 0 : 85000000,
    ]);
}

test('admin can list create show and update residents', function () {
    $admin = tb2Admin();

    $create = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/residents', tb2ResidentPayload('tb2.resident.'.uniqid().'@rosewoodroyale.com'))
        ->assertCreated()
        ->assertJsonPath('data.role_name', Role::CUSTOMER)
        ->assertJsonStructure([
            'message',
            'data' => ['id', 'name', 'email', 'role_name', 'phone', 'nrc'],
        ]);

    $residentId = $create->json('data.id');

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/residents')
        ->assertOk()
        ->assertJsonStructure(['data' => ['data', 'total']]);

    $this->actingAs($admin, 'sanctum')
        ->getJson("/api/residents/{$residentId}")
        ->assertOk()
        ->assertJsonPath('data.id', $residentId);

    $this->actingAs($admin, 'sanctum')
        ->putJson("/api/residents/{$residentId}", [
            'name' => 'TB2 Customer Updated',
            'email' => $create->json('data.email'),
            'phone' => '09-99998888',
            'nrc' => '12/ABC(N)123456',
            'dob' => '1990-01-15',
            'gender' => 'male',
            'address' => 'Updated address',
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'TB2 Customer Updated');
});

test('resident validation rejects missing and duplicate fields', function () {
    $admin = tb2Admin();

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/residents', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name', 'email', 'password', 'phone', 'nrc', 'dob', 'gender', 'address'], 'data');

    $email = 'tb2.dup.'.uniqid().'@rosewoodroyale.com';
    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/residents', tb2ResidentPayload($email))
        ->assertCreated();

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/residents', tb2ResidentPayload($email))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email'], 'data');
});

test('admin can create and approve rent contract draft workflow', function () {
    $admin = tb2Admin();
    $customer = tb2Customer();
    $room = tb2CreateRoom('rent', 'TB2-R-1');

    $draftId = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/rent-contract-drafts', [
            'user_id' => $customer->id,
            'room_id' => $room->id,
            'payment_type' => 'full',
            'start_date' => now()->toDateString(),
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.type', 'rent')
        ->assertJsonPath('data.contract_number', 'R-000001')
        ->json('data.id');

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/rent-contract-drafts/{$draftId}/approve")
        ->assertOk()
        ->assertJsonPath('data.status', 'active');

    expect($room->fresh()->status)->toBe('occupied');
    expect(Contract::query()->find($draftId)?->user_id)->toBe($customer->id);
    expect(Contract::query()->find($draftId)?->room_id)->toBe($room->id);

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/rent-contracts/active')
        ->assertOk()
        ->assertJsonPath('data.total', 1);
});

test('admin can create and approve sale contract draft workflow', function () {
    $admin = tb2Admin();
    $customer = tb2Customer();
    $room = tb2CreateRoom('sale', 'TB2-S-1');

    $draftId = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/sale-contract-drafts', [
            'user_id' => $customer->id,
            'room_id' => $room->id,
            'payment_type' => 'full',
            'start_date' => now()->addDay()->toDateString(),
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.type', 'sale')
        ->json('data.id');

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/sale-contract-drafts/{$draftId}/approve")
        ->assertOk()
        ->assertJsonPath('data.status', 'approved');

    expect($room->fresh()->status)->toBe('reserved');

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/sale-contracts/approved')
        ->assertOk()
        ->assertJsonPath('data.total', 1);
});

test('contract draft validation rejects missing fields and installment without duration', function () {
    $admin = tb2Admin();
    $customer = tb2Customer();
    $room = tb2CreateRoom('rent', 'TB2-V-1');

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/rent-contract-drafts', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['user_id', 'room_id', 'payment_type', 'start_date'], 'data');

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/rent-contract-drafts', [
            'user_id' => $customer->id,
            'room_id' => $room->id,
            'payment_type' => 'installment',
            'start_date' => now()->toDateString(),
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['duration_months', 'billing_day'], 'data');
});

test('admin can create and retrieve payment plans', function () {
    $admin = tb2Admin();

    $create = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/payment-plans', [
            'name' => 'TB2 Custom Plan',
            'payment_type' => 'installment',
            'duration_months' => 12,
            'interest_percentage' => 2.5,
            'status' => 'active',
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'TB2 Custom Plan')
        ->assertJsonPath('data.payment_type', 'installment');

    $planId = $create->json('data.id');

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/payment-plans')
        ->assertOk()
        ->assertJsonStructure(['data' => ['data', 'total']]);

    $this->actingAs($admin, 'sanctum')
        ->getJson("/api/payment-plans/{$planId}")
        ->assertOk()
        ->assertJsonPath('data.id', $planId);
});

test('customer can view own contract and is forbidden from another customers contract', function () {
    $admin = tb2Admin();
    $customer = tb2Customer();
    $other = tb2OtherCustomer();

    $ownRoom = tb2CreateRoom('rent', 'TB2-OWN');
    $otherRoom = tb2CreateRoom('rent', 'TB2-OTH');

    $ownContract = Contract::query()->create([
        'contract_number' => 'R-TB2-OWN',
        'user_id' => $customer->id,
        'room_id' => $ownRoom->id,
        'contract_total' => 1200000,
        'deposit_amount' => 2400000,
        'type' => 'rent',
        'payment_type' => 'full',
        'duration_months' => 12,
        'billing_day' => 1,
        'status' => 'active',
        'created_by' => $admin->id,
        'start_date' => now()->toDateString(),
    ]);

    $otherContract = Contract::query()->create([
        'contract_number' => 'R-TB2-OTH',
        'user_id' => $other->id,
        'room_id' => $otherRoom->id,
        'contract_total' => 1200000,
        'deposit_amount' => 2400000,
        'type' => 'rent',
        'payment_type' => 'full',
        'duration_months' => 12,
        'billing_day' => 1,
        'status' => 'active',
        'created_by' => $admin->id,
        'start_date' => now()->toDateString(),
    ]);

    $this->actingAs($customer, 'sanctum')
        ->getJson('/api/customer/contracts')
        ->assertOk()
        ->assertJsonPath('data.total', 1)
        ->assertJsonPath('data.data.0.id', $ownContract->id);

    $this->actingAs($customer, 'sanctum')
        ->getJson("/api/customer/contracts/{$ownContract->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $ownContract->id);

    $this->actingAs($customer, 'sanctum')
        ->getJson("/api/customer/contracts/{$otherContract->id}")
        ->assertNotFound();
});

test('customer cannot access administrator resident or contract endpoints', function () {
    $admin = tb2Admin();
    $customer = tb2Customer();

    $this->actingAs($customer, 'sanctum')->getJson('/api/residents')->assertForbidden();
    $this->actingAs($customer, 'sanctum')->getJson('/api/rent-contract-drafts')->assertForbidden();
    $this->actingAs($customer, 'sanctum')->getJson('/api/sale-contract-drafts')->assertForbidden();
    $this->actingAs($customer, 'sanctum')->getJson('/api/payment-plans')->assertForbidden();

    $this->actingAs($admin, 'sanctum')->getJson('/api/residents')->assertOk();
});

test('timebox 2 model relationships and seeder idempotency', function () {
    $admin = tb2Admin();
    $customer = tb2Customer();
    $room = tb2CreateRoom('rent', 'TB2-REL');

    $contract = Contract::query()->create([
        'contract_number' => 'R-TB2-REL',
        'user_id' => $customer->id,
        'room_id' => $room->id,
        'contract_total' => 1200000,
        'type' => 'rent',
        'payment_type' => 'full',
        'status' => 'active',
        'created_by' => $admin->id,
    ]);

    expect($contract->user->id)->toBe($customer->id);
    expect($contract->room->id)->toBe($room->id);
    expect($room->contracts)->toHaveCount(1);
    expect($customer->fresh()->profile)->not->toBeNull();

    $planCount = PaymentPlan::query()->count();
    $userCount = User::query()->count();
    (new PaymentPlanSeeder)->run();
    (new UserSeeder)->run();
    (new RoleSeeder)->run();
    expect(PaymentPlan::query()->count())->toBe($planCount);
    expect(User::query()->count())->toBe($userCount);
});
