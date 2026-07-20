<?php

use App\Models\Building;
use App\Models\Contract;
use App\Models\Role;
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

    $customer = User::query()->where('email', 'mgmg@rosewoodroyale.com')->firstOrFail();

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
        ->assertJsonPath('data.status', 'draft')
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

test('installment sale contract draft requires duration and billing day', function () {
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
        ->assertJsonValidationErrors(['duration_months', 'billing_day'], 'data');

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
        ->assertJsonValidationErrors(['duration_months'], 'data');
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
            'billing_day' => 5,
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

test('show sale contract draft returns relationships and computed payment summary', function () {
    $admin = saleDraftAdmin();
    ['room' => $room, 'customer' => $customer, 'building' => $building] = seedSaleDraftStack();

    $create = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/sale-contract-drafts', [
            'user_id' => $customer->id,
            'room_id' => $room->id,
            'payment_type' => 'installment',
            'duration_months' => 12,
            'billing_day' => 5,
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
        ->assertJsonPath('data.billing_day', 5)
        ->assertJsonPath('data.remark', 'Customer requested flexible billing.')
        ->assertJsonPath('data.estimated_monthly_payment', '63750000.00');
});
