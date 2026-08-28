<?php

use App\Models\Building;
use App\Models\Contract;
use App\Models\Room;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function saleDraftAdmin(): User
{
    (new RoleSeeder)->run();
    (new UserSeeder)->run();

    return User::query()->where('email', 'admin@rosewoodroyale.com')->firstOrFail();
}

function seedSaleDraftStack(): array
{
    $building = Building::query()->create([
        'building_name' => 'Rosewood Tower',
        'location' => 'Yangon',
    ]);

    $room = Room::query()->create([
        'building_id' => $building->id,
        'room_number' => 'A-1201',
        'floor_number' => 12,
        'type' => 'sale',
        'status' => 'available',
        'area_sqft' => 1200,
        'sale_price' => 850000000,
        'rent_price' => 0,
        'rent_deposit_price' => 0,
        'booking_deposit_price' => 85000000,
    ]);

    $customer = User::query()->where('email', 'mgmg@gmail.com')->firstOrFail();

    return compact('building', 'room', 'customer');
}

test('admin can create sale contract draft with auto generated number and defaults', function () {
    $admin = saleDraftAdmin();
    ['room' => $room, 'customer' => $customer] = seedSaleDraftStack();

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/sale-contract-drafts', [
            'user_id' => $customer->id,
            'room_id' => $room->id,
            'payment_type' => 'full',
            'start_date' => now()->toDateString(),
        ])
        ->assertCreated()
        ->assertJsonPath('data.contract_number', 'S-000001')
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.type', 'sale')
        ->assertJsonPath('data.contract_total', '850000000.00')
        ->assertJsonPath('data.deposit_amount', '85000000.00')
        ->assertJsonPath('data.room_price', '850000000.00')
        ->assertJsonPath('data.approved_by', null)
        ->assertJsonPath('data.approved_at', null);
});

test('sale contract numbers increment and never reuse deleted numbers', function () {
    $admin = saleDraftAdmin();
    ['room' => $room, 'customer' => $customer] = seedSaleDraftStack();

    $first = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/sale-contract-drafts', [
            'user_id' => $customer->id,
            'room_id' => $room->id,
            'payment_type' => 'full',
            'start_date' => now()->toDateString(),
        ])
        ->assertCreated()
        ->json('data.id');

    $this->actingAs($admin, 'sanctum')
        ->deleteJson("/api/sale-contract-drafts/{$first}")
        ->assertOk();

    $room->update(['status' => 'available']);

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/sale-contract-drafts', [
            'user_id' => $customer->id,
            'room_id' => $room->id,
            'payment_type' => 'full',
            'start_date' => now()->toDateString(),
        ])
        ->assertCreated()
        ->assertJsonPath('data.contract_number', 'S-000002');
});

test('installment sale contract draft requires duration and rejects billing day', function () {
    $admin = saleDraftAdmin();
    ['room' => $room, 'customer' => $customer] = seedSaleDraftStack();

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/sale-contract-drafts', [
            'user_id' => $customer->id,
            'room_id' => $room->id,
            'payment_type' => 'installment',
            'start_date' => now()->toDateString(),
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['duration_months'], 'data');

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/sale-contract-drafts', [
            'user_id' => $customer->id,
            'room_id' => $room->id,
            'payment_type' => 'installment',
            'duration_months' => 5,
            'billing_day' => 15,
            'start_date' => now()->toDateString(),
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['duration_months', 'billing_day'], 'data');
});

test('sale contract draft rejects unavailable room and invalid totals', function () {
    $admin = saleDraftAdmin();
    ['room' => $room, 'customer' => $customer] = seedSaleDraftStack();

    $room->update(['status' => 'sold']);

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/sale-contract-drafts', [
            'user_id' => $customer->id,
            'room_id' => $room->id,
            'payment_type' => 'full',
            'start_date' => now()->toDateString(),
        ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'Room is not available for contract.');

    $room->update(['status' => 'available', 'type' => 'rent']);

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/sale-contract-drafts', [
            'user_id' => $customer->id,
            'room_id' => $room->id,
            'payment_type' => 'full',
            'start_date' => now()->toDateString(),
        ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'Selected room is not available for sale.');

    $room->update(['type' => 'sale']);

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/sale-contract-drafts', [
            'user_id' => $customer->id,
            'room_id' => $room->id,
            'payment_type' => 'full',
            'contract_total' => 0,
            'start_date' => now()->toDateString(),
        ])
        ->assertStatus(422);

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/sale-contract-drafts', [
            'user_id' => $customer->id,
            'room_id' => $room->id,
            'payment_type' => 'full',
            'start_date' => now()->subDay()->toDateString(),
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['start_date'], 'data');
});

test('admin can list update and delete sale contract drafts', function () {
    $admin = saleDraftAdmin();
    ['room' => $room, 'customer' => $customer] = seedSaleDraftStack();

    $create = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/sale-contract-drafts', [
            'user_id' => $customer->id,
            'room_id' => $room->id,
            'payment_type' => 'installment',
            'duration_months' => 12,
            'contract_total' => 900000000,
            'start_date' => now()->toDateString(),
            'remark' => 'Initial draft',
        ])
        ->assertCreated();

    $contractId = $create->json('data.id');

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/sale-contract-drafts')
        ->assertOk()
        ->assertJsonPath('data.total', 1);

    $this->actingAs($admin, 'sanctum')
        ->putJson("/api/sale-contract-drafts/{$contractId}", [
            'remark' => 'Updated draft',
            'payment_type' => 'full',
        ])
        ->assertOk()
        ->assertJsonPath('data.remark', 'Updated draft')
        ->assertJsonPath('data.duration_months', null)
        ->assertJsonPath('data.billing_day', null);

    $this->actingAs($admin, 'sanctum')
        ->deleteJson("/api/sale-contract-drafts/{$contractId}")
        ->assertOk();

    expect(Contract::withTrashed()->find($contractId)?->trashed())->toBeTrue();
});

test('sale contract draft list filters search payment type and created dates before pagination', function () {
    $admin = saleDraftAdmin();

    $building = Building::query()->create([
        'building_name' => 'Filter Tower',
        'location' => 'Yangon',
    ]);

    $makeRoom = fn (string $roomNumber): Room => Room::query()->create([
        'building_id' => $building->id,
        'room_number' => $roomNumber,
        'floor_number' => 1,
        'type' => 'sale',
        'status' => 'available',
        'area_sqft' => 1000,
        'sale_price' => 500000000,
        'rent_price' => 0,
        'rent_deposit_price' => 0,
        'booking_deposit_price' => 50000000,
    ]);

    $makeCustomer = fn (string $name): User => User::factory()->customer()->create([
        'name' => $name,
        'status' => User::STATUS_ACTIVE,
    ]);

    $createDraft = function (
        string $contractNumber,
        User $customer,
        Room $room,
        string $paymentType,
        string $createdAt,
    ) use ($admin): Contract {
        $contract = Contract::query()->create([
            'contract_number' => $contractNumber,
            'user_id' => $customer->id,
            'room_id' => $room->id,
            'created_by' => $admin->id,
            'contract_total' => 500000000,
            'deposit_amount' => 50000000,
            'type' => 'sale',
            'payment_type' => $paymentType,
            'duration_months' => $paymentType === 'installment' ? 12 : null,
            'start_date' => '2026-08-01',
            'status' => Contract::STATUS_PENDING,
        ]);

        $contract->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->saveQuietly();

        return $contract;
    };

    $augustFull = $createDraft('S-000101', $makeCustomer('Daw Aye Aye'), $makeRoom('A-101'), 'full', '2026-08-05 10:00:00');
    $augustInstallment = $createDraft('S-000102', $makeCustomer('U Khin Maung'), $makeRoom('B-202'), 'installment', '2026-08-31 23:59:59');
    $createDraft('S-000103', $makeCustomer('Daw Hnin Yu'), $makeRoom('C-303'), 'full', '2026-07-31 23:59:59');
    $createDraft('S-000104', $makeCustomer('U Min Thu'), $makeRoom('D-404'), 'installment', '2026-09-01 00:00:00');

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/sale-contract-drafts?search=aye&per_page=1')
        ->assertOk()
        ->assertJsonPath('data.total', 1)
        ->assertJsonPath('data.data.0.id', $augustFull->id);

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/sale-contract-drafts?search=B-20')
        ->assertOk()
        ->assertJsonPath('data.total', 1)
        ->assertJsonPath('data.data.0.id', $augustInstallment->id);

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/sale-contract-drafts?payment_type=full')
        ->assertOk()
        ->assertJsonPath('data.total', 2);

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/sale-contract-drafts?date_from=2026-08-01&date_to=2026-08-31')
        ->assertOk()
        ->assertJsonPath('data.total', 2);

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/sale-contract-drafts?search=khin&payment_type=installment&date_from=2026-08-01&date_to=2026-08-31')
        ->assertOk()
        ->assertJsonPath('data.total', 1)
        ->assertJsonPath('data.data.0.id', $augustInstallment->id);
});

test('show sale contract draft returns relationships and computed payment summary', function () {
    $admin = saleDraftAdmin();
    ['room' => $room, 'customer' => $customer, 'building' => $building] = seedSaleDraftStack();

    $create = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/sale-contract-drafts', [
            'user_id' => $customer->id,
            'room_id' => $room->id,
            'payment_type' => 'installment',
            'duration_months' => 12,
            'contract_total' => 850000000,
            'start_date' => now()->toDateString(),
            'remark' => 'Customer requested flexible billing.',
        ])
        ->assertCreated();

    $contractId = $create->json('data.id');

    $this->actingAs($admin, 'sanctum')
        ->getJson("/api/sale-contract-drafts/{$contractId}")
        ->assertOk()
        ->assertJsonPath('data.customer.id', $customer->id)
        ->assertJsonPath('data.room.id', $room->id)
        ->assertJsonPath('data.building.id', $building->id)
        ->assertJsonPath('data.room_price', '850000000.00')
        ->assertJsonPath('data.deposit_amount', '85000000.00')
        ->assertJsonPath('data.remaining_balance', '765000000.00')
        ->assertJsonPath('data.duration_months', 12)
        ->assertJsonPath('data.billing_day', null)
        ->assertJsonPath('data.remark', 'Customer requested flexible billing.')
        ->assertJsonPath('data.estimated_monthly_payment', '63750000.00');
});
