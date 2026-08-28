<?php

use App\Models\Contract;
use App\Models\User;
use App\Models\Utility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

test('utility records require consecutive billing months and reading dates', function () {
    $admin = tb3Admin();
    $customer = User::query()->where('email', 'mgmg@gmail.com')->firstOrFail();
    $start = Carbon::parse('2025-08-01');
    ['room' => $room, 'contract' => $contract, 'utilityType' => $utilityType, 'rate' => $rate] = tb3RentPeriodStack(
        $admin,
        $customer,
        'UP-101',
        $start,
        Carbon::parse('2025-11-30'),
    );

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/utilities', [
            'room_id' => $room->id,
            'billing_month' => '2025-10-01',
            'utility_items' => [[
                'utility_type_id' => $utilityType->id,
                'previous_reading' => 0,
                'current_reading' => 100,
                'unit_price' => (float) $rate->unit_price,
            ]],
        ])
        ->assertStatus(422)
        ->assertJsonPath('data.billing_month.0', 'Please enter the August 2025 utility record first for this contract.');

    $august = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/utilities', [
            'room_id' => $room->id,
            'billing_month' => '2025-08-01',
            'reading_date' => '2025-09-01',
            'utility_items' => [[
                'utility_type_id' => $utilityType->id,
                'previous_reading' => 0,
                'current_reading' => 100,
                'unit_price' => (float) $rate->unit_price,
            ]],
        ])
        ->assertCreated()
        ->assertJsonPath('data.contract_id', $contract->id)
        ->assertJsonPath('data.billing_month', '2025-08-01')
        ->assertJsonPath('data.reading_date', '2025-09-01')
        ->json('data');

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/utilities', [
            'room_id' => $room->id,
            'billing_month' => '2025-10-01',
            'utility_items' => [[
                'utility_type_id' => $utilityType->id,
                'previous_reading' => 100,
                'current_reading' => 200,
                'unit_price' => (float) $rate->unit_price,
            ]],
        ])
        ->assertStatus(422)
        ->assertJsonPath('data.billing_month.0', 'Please enter the September utility record before adding October.');

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/utilities', [
            'room_id' => $room->id,
            'billing_month' => '2025-09-01',
            'reading_date' => '2025-10-01',
            'utility_items' => [[
                'utility_type_id' => $utilityType->id,
                'previous_reading' => 100,
                'current_reading' => 180,
                'unit_price' => (float) $rate->unit_price,
            ]],
        ])
        ->assertCreated()
        ->assertJsonPath('data.billing_month', '2025-09-01');

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/utilities', [
            'room_id' => $room->id,
            'billing_month' => '2025-09-01',
            'utility_items' => [[
                'utility_type_id' => $utilityType->id,
                'previous_reading' => 180,
                'current_reading' => 220,
                'unit_price' => (float) $rate->unit_price,
            ]],
        ])
        ->assertStatus(422)
        ->assertJsonPath('data.billing_month.0', 'A utility record already exists for September 2025 on this contract.');

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/utilities', [
            'room_id' => $room->id,
            'billing_month' => '2025-12-01',
            'utility_items' => [[
                'utility_type_id' => $utilityType->id,
                'previous_reading' => 180,
                'current_reading' => 240,
                'unit_price' => (float) $rate->unit_price,
            ]],
        ])
        ->assertStatus(422)
        ->assertJsonPath('data.billing_month.0', 'Utility billing cannot continue after the contract end month (November 2025).');

    expect(Utility::query()->find($august['id'])?->contract_id)->toBe($contract->id);
});

test('new contract starts a fresh utility sequence without transferring prior history', function () {
    $admin = tb3Admin();
    $customerA = User::query()->where('email', 'mgmg@gmail.com')->firstOrFail();
    $customerB = User::query()->where('email', 'hlahla@gmail.com')->firstOrFail();

    ['room' => $room, 'contract' => $contractA, 'utilityType' => $utilityType, 'rate' => $rate] = tb3RentPeriodStack(
        $admin,
        $customerA,
        'UP-201',
        Carbon::parse('2025-08-01'),
        Carbon::parse('2025-11-30'),
    );

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/utilities', [
            'room_id' => $room->id,
            'billing_month' => '2025-08-01',
            'utility_items' => [[
                'utility_type_id' => $utilityType->id,
                'previous_reading' => 0,
                'current_reading' => 50,
                'unit_price' => (float) $rate->unit_price,
            ]],
        ])
        ->assertCreated();

    $contractA->update(['status' => 'completed']);

    $contractB = Contract::query()->create([
        'contract_number' => 'R-UP-'.fake()->unique()->numerify('######'),
        'user_id' => $customerB->id,
        'room_id' => $room->id,
        'contract_total' => 4800000,
        'deposit_amount' => 800000,
        'type' => 'rent',
        'payment_type' => 'full',
        'status' => 'active',
        'created_by' => $admin->id,
        'start_date' => '2025-12-01',
        'end_date' => '2026-11-30',
    ]);

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/utilities', [
            'room_id' => $room->id,
            'billing_month' => '2025-12-01',
            'reading_date' => '2026-01-01',
            'utility_items' => [[
                'utility_type_id' => $utilityType->id,
                'previous_reading' => 0,
                'current_reading' => 40,
                'unit_price' => (float) $rate->unit_price,
            ]],
        ])
        ->assertCreated()
        ->assertJsonPath('data.contract_id', $contractB->id)
        ->assertJsonPath('data.billing_month', '2025-12-01');

    expect(Utility::query()->where('contract_id', $contractA->id)->count())->toBe(1);
    expect(Utility::query()->where('contract_id', $contractB->id)->count())->toBe(1);
    expect(Utility::query()->where('room_id', $room->id)->count())->toBe(2);
});

test('sale room utility billing continues for the owner after the sale contract end date', function () {
    $admin = tb3Admin();
    $customer = User::query()->where('email', 'mgmg@gmail.com')->firstOrFail();

    ['room' => $room, 'contract' => $contract, 'utilityType' => $utilityType, 'rate' => $rate] = tb3SalePeriodStack(
        $admin,
        $customer,
        'US-101',
        Carbon::parse('2025-01-01'),
        Carbon::parse('2025-06-30'),
    );

    $previousReading = 0;
    foreach (range(1, 6) as $month) {
        $currentReading = $previousReading + 10;

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/utilities', [
                'room_id' => $room->id,
                'billing_month' => Carbon::create(2025, $month, 1)->toDateString(),
                'reading_date' => Carbon::create(2025, $month, 15)->toDateString(),
                'utility_items' => [[
                    'utility_type_id' => $utilityType->id,
                    'previous_reading' => $previousReading,
                    'current_reading' => $currentReading,
                    'unit_price' => (float) $rate->unit_price,
                ]],
            ])
            ->assertCreated()
            ->assertJsonPath('data.contract_id', $contract->id);

        $previousReading = $currentReading;
    }

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/utilities', [
            'room_id' => $room->id,
            'billing_month' => '2025-07-01',
            'reading_date' => '2025-07-15',
            'utility_items' => [[
                'utility_type_id' => $utilityType->id,
                'previous_reading' => 60,
                'current_reading' => 75,
                'unit_price' => (float) $rate->unit_price,
            ]],
        ])
        ->assertCreated()
        ->assertJsonPath('data.contract_id', $contract->id);

    $bulkPreview = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/utilities/bulk-import/preview', [
            'utility_type_id' => $utilityType->id,
            'rows' => [[
                'building' => $room->building->building_name,
                'room_number' => $room->room_number,
                'billing_month' => '01/08/2025',
                'reading_date' => '15/08/2025',
                'current_reading' => 90,
            ]],
        ])
        ->assertOk()
        ->json('data.rows.0');

    expect($bulkPreview['is_valid'])->toBeTrue();
    expect($bulkPreview['contract_id'])->toBe($contract->id);
    expect((float) $bulkPreview['previous_reading'])->toBe(75.0);
});

test('rent room utility billing remains bounded by the rental period and resolves a new valid rent contract', function () {
    $admin = tb3Admin();
    $customerA = User::query()->where('email', 'mgmg@gmail.com')->firstOrFail();
    $customerB = User::query()->where('email', 'hlahla@gmail.com')->firstOrFail();

    ['room' => $room, 'contract' => $contractA, 'utilityType' => $utilityType, 'rate' => $rate] = tb3RentPeriodStack(
        $admin,
        $customerA,
        'UR-101',
        Carbon::parse('2025-08-01'),
        Carbon::parse('2025-08-31'),
    );

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/utilities', [
            'room_id' => $room->id,
            'billing_month' => '2025-09-01',
            'reading_date' => '2025-09-15',
            'utility_items' => [[
                'utility_type_id' => $utilityType->id,
                'previous_reading' => 0,
                'current_reading' => 100,
                'unit_price' => (float) $rate->unit_price,
            ]],
        ])
        ->assertStatus(422)
        ->assertJsonPath('data.billing_month.0', 'Utility billing cannot continue after the contract end month (August 2025).');

    $contractA->update(['status' => 'completed']);

    $contractB = Contract::query()->create([
        'contract_number' => 'R-UP-'.fake()->unique()->numerify('######'),
        'user_id' => $customerB->id,
        'room_id' => $room->id,
        'contract_total' => 4800000,
        'deposit_amount' => 800000,
        'type' => 'rent',
        'payment_type' => 'full',
        'status' => 'active',
        'created_by' => $admin->id,
        'start_date' => '2025-09-01',
        'end_date' => '2026-08-31',
    ]);

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/utilities', [
            'room_id' => $room->id,
            'billing_month' => '2025-09-01',
            'reading_date' => '2025-09-15',
            'utility_items' => [[
                'utility_type_id' => $utilityType->id,
                'previous_reading' => 0,
                'current_reading' => 100,
                'unit_price' => (float) $rate->unit_price,
            ]],
        ])
        ->assertCreated()
        ->assertJsonPath('data.contract_id', $contractB->id);
});

