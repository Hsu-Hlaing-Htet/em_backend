<?php

use App\Models\Contract;
use App\Models\User;
use App\Models\Utility;
use App\Models\UtilityType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

test('bulk utility import resolves previous reading from latest room meter history across contracts', function () {
    $admin = tb3Admin();
    $customerA = User::query()->where('email', 'mgmg@gmail.com')->firstOrFail();
    $customerB = User::query()->where('email', 'hlahla@gmail.com')->firstOrFail();

    ['room' => $room, 'contract' => $contractA, 'utilityType' => $utilityType, 'rate' => $rate] = tb3RentPeriodStack(
        $admin,
        $customerA,
        'UP-251',
        Carbon::parse('2025-08-01'),
        Carbon::parse('2025-08-31'),
    );

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/utilities', [
            'room_id' => $room->id,
            'billing_month' => '2025-08-01',
            'reading_date' => '2025-08-15',
            'utility_items' => [[
                'utility_type_id' => $utilityType->id,
                'previous_reading' => 0,
                'current_reading' => 125,
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
        'start_date' => '2025-09-01',
        'end_date' => '2026-08-31',
    ]);

    $preview = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/utilities/bulk-import/preview', [
            'utility_type_id' => $utilityType->id,
            'rows' => [[
                'building' => $room->building->building_name,
                'room_number' => $room->room_number,
                'billing_month' => '01/09/2025',
                'reading_date' => '15/09/2025',
                'current_reading' => 150,
            ]],
        ])
        ->assertOk()
        ->json('data.rows.0');

    expect($preview['is_valid'])->toBeTrue();
    expect((float) $preview['previous_reading'])->toBe(125.0);
    expect($preview['contract_id'])->toBe($contractB->id);

    $invalidReadingPreview = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/utilities/bulk-import/preview', [
            'utility_type_id' => $utilityType->id,
            'rows' => [[
                'building' => $room->building->building_name,
                'room_number' => $room->room_number,
                'billing_month' => '01/09/2025',
                'reading_date' => '15/09/2025',
                'current_reading' => 120,
            ]],
        ])
        ->assertOk()
        ->json('data.rows.0');

    expect($invalidReadingPreview['is_valid'])->toBeFalse();
    expect($invalidReadingPreview['messages'])->toContain('Current reading cannot be less than previous reading.');

    $response = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/utilities/bulk-import/confirm', [
            'utility_type_id' => $utilityType->id,
            'rows' => [[
                'building' => $room->building->building_name,
                'room_number' => $room->room_number,
                'billing_month' => '01/09/2025',
                'reading_date' => '15/09/2025',
                'current_reading' => 150,
            ]],
        ])
        ->assertCreated()
        ->json('data.0');

    $utility = Utility::query()
        ->with('items')
        ->findOrFail($response['id']);

    expect($utility->contract_id)->toBe($contractB->id);
    expect((float) $utility->items->first()->previous_reading)->toBe(125.0);
    expect((float) $utility->items->first()->current_reading)->toBe(150.0);
    expect((float) $utility->items->first()->usage)->toBe(25.0);
});

test('bulk utility import requires an active utility type', function () {
    $admin = tb3Admin();
    $customer = User::query()->where('email', 'mgmg@gmail.com')->firstOrFail();
    ['room' => $room] = tb3RentPeriodStack(
        $admin,
        $customer,
        'UP-275',
        Carbon::parse('2025-08-01'),
        Carbon::parse('2025-11-30'),
    );
    $inactiveType = UtilityType::factory()->create(['status' => 'inactive']);

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/utilities/bulk-import/preview', [
            'utility_type_id' => $inactiveType->id,
            'rows' => [[
                'building' => $room->building->building_name,
                'room_number' => $room->room_number,
                'billing_month' => '01/08/2025',
                'reading_date' => '15/08/2025',
                'current_reading' => 150,
            ]],
        ])
        ->assertStatus(422);
});

test('bulk utility import accepts short Excel formatted billing month dates', function () {
    $admin = tb3Admin();
    $customer = User::query()->where('email', 'mgmg@gmail.com')->firstOrFail();
    ['room' => $room, 'contract' => $contract, 'utilityType' => $utilityType] = tb3RentPeriodStack(
        $admin,
        $customer,
        'UP-285',
        Carbon::parse('2026-03-01'),
        Carbon::parse('2026-06-30'),
    );

    $preview = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/utilities/bulk-import/preview', [
            'utility_type_id' => $utilityType->id,
            'rows' => [[
                'building' => $room->building->building_name,
                'room_number' => $room->room_number,
                'billing_month' => '1/3/26',
                'reading_date' => '15/03/2026',
                'current_reading' => 150,
            ]],
        ])
        ->assertOk()
        ->json('data.rows.0');

    expect($preview)->toMatchArray([
        'is_valid' => true,
        'billing_month' => '01/03/2026',
        'contract_id' => $contract->id,
    ]);
});

test('bulk utility import preview validates matched data without creating records', function () {
    $admin = tb3Admin();
    $customer = User::query()->where('email', 'mgmg@gmail.com')->firstOrFail();
    $start = Carbon::parse('2025-08-01');
    ['room' => $room, 'contract' => $contract, 'utilityType' => $utilityType] = tb3RentPeriodStack(
        $admin,
        $customer,
        'UP-301',
        $start,
        Carbon::parse('2025-11-30'),
    );

    $response = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/utilities/bulk-import/preview', [
            'utility_type_id' => $utilityType->id,
            'rows' => [
                [
                    'building' => $room->building->building_name,
                    'room_number' => $room->room_number,
                    'billing_month' => '01/08/2025',
                    'reading_date' => '15/08/2025',
                    'current_reading' => 125,
                ],
                [
                    'building' => $room->building->building_name,
                    'room_number' => $room->room_number,
                    'billing_month' => 45870,
                    'reading_date' => '20/08/2025',
                    'current_reading' => 130,
                ],
                [
                    'building' => $room->building->building_name,
                    'room_number' => 'NO-ROOM',
                    'billing_month' => '2025-08',
                    'reading_date' => '01/09/2025',
                    'current_reading' => 150,
                ],
            ],
        ])
        ->assertOk()
        ->json('data');

    expect($response['summary'])->toMatchArray([
        'total_rows' => 3,
        'valid_rows' => 1,
        'invalid_rows' => 2,
    ]);
    expect($response['rows'][0])->toMatchArray([
        'status' => 'Valid',
        'is_valid' => true,
        'billing_month' => '01/08/2025',
        'building_id' => $room->building_id,
        'room_id' => $room->id,
        'utility_type_id' => $utilityType->id,
        'contract_id' => $contract->id,
        'contract_number' => $contract->contract_number,
        'customer_email' => $customer->email,
        'previous_reading' => 0.0,
        'utility_type' => $utilityType->name,
    ]);
    expect($response['rows'][1]['billing_month'])->toBe('01/08/2025');
    expect($response['rows'][1]['messages'])->toContain('Duplicate row in this file. Already listed on row 1.');
    expect($response['rows'][2]['messages'])->toContain('Room not found.');
    expect($response['rows'][2]['messages'])->toContain('Invalid billing month. Use DD/MM/YYYY format.');
    expect(Utility::query()->count())->toBe(0);
});

test('bulk utility import validates sequential months using valid earlier rows in the same file', function () {
    $admin = tb3Admin();
    $customer = User::query()->where('email', 'mgmg@gmail.com')->firstOrFail();
    ['room' => $room, 'utilityType' => $utilityType] = tb3RentPeriodStack(
        $admin,
        $customer,
        'UP-401',
        Carbon::parse('2025-08-01'),
        Carbon::parse('2025-11-30'),
    );

    $preview = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/utilities/bulk-import/preview', [
            'utility_type_id' => $utilityType->id,
            'rows' => [
                [
                    'building' => $room->building->building_name,
                    'room_number' => $room->room_number,
                    'billing_month' => '01/09/2025',
                    'reading_date' => '15/09/2025',
                    'current_reading' => 155,
                ],
                [
                    'building' => $room->building->building_name,
                    'room_number' => $room->room_number,
                    'billing_month' => '01/08/2025',
                    'reading_date' => '15/08/2025',
                    'current_reading' => 125,
                ],
            ],
        ])
        ->assertOk()
        ->json('data');

    expect($preview['summary'])->toMatchArray([
        'total_rows' => 2,
        'valid_rows' => 2,
        'invalid_rows' => 0,
    ]);
    expect($preview['rows'][0])->toMatchArray([
        'billing_month' => '01/09/2025',
        'is_valid' => true,
        'previous_reading' => 125.0,
    ]);
    expect($preview['rows'][1])->toMatchArray([
        'billing_month' => '01/08/2025',
        'is_valid' => true,
        'previous_reading' => 0.0,
    ]);

    $response = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/utilities/bulk-import/confirm', [
            'utility_type_id' => $utilityType->id,
            'rows' => [
                [
                    'building' => $room->building->building_name,
                    'room_number' => $room->room_number,
                    'billing_month' => '01/09/2025',
                    'reading_date' => '15/09/2025',
                    'current_reading' => 155,
                ],
                [
                    'building' => $room->building->building_name,
                    'room_number' => $room->room_number,
                    'billing_month' => '01/08/2025',
                    'reading_date' => '15/08/2025',
                    'current_reading' => 125,
                ],
            ],
        ])
        ->assertCreated()
        ->assertJsonPath('message', '2 utilities imported successfully.')
        ->json('data');

    expect($response)->toHaveCount(2);

    $august = Utility::query()
        ->with('items')
        ->where('room_id', $room->id)
        ->whereDate('billing_month', '2025-08-01')
        ->firstOrFail();
    $september = Utility::query()
        ->with('items')
        ->where('room_id', $room->id)
        ->whereDate('billing_month', '2025-09-01')
        ->firstOrFail();

    expect($august->status)->toBe('pending');
    expect($august->reading_date->toDateString())->toBe('2025-08-15');
    expect((float) $august->items->first()->previous_reading)->toBe(0.0);
    expect((float) $august->items->first()->current_reading)->toBe(125.0);
    expect((float) $august->items->first()->usage)->toBe(125.0);
    expect((float) $september->items->first()->previous_reading)->toBe(125.0);
    expect((float) $september->items->first()->current_reading)->toBe(155.0);
    expect((float) $september->items->first()->usage)->toBe(30.0);
    expect(Utility::query()->count())->toBe(2);

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/utilities?status=pending')
        ->assertOk()
        ->assertJsonPath('data.total', 2);

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/utilities/bulk-import/confirm', [
            'utility_type_id' => $utilityType->id,
            'rows' => [[
                'building' => $room->building->building_name,
                'room_number' => $room->room_number,
                'billing_month' => '01/08/2025',
                'reading_date' => '15/08/2025',
                'current_reading' => 125,
            ]],
        ])
        ->assertStatus(422)
        ->assertJsonPath('data.rows.0', 'No valid utility rows available to import.');
});

test('bulk utility import confirm is disabled server-side when any row is invalid', function () {
    $admin = tb3Admin();
    $customer = User::query()->where('email', 'mgmg@gmail.com')->firstOrFail();
    ['room' => $room, 'utilityType' => $utilityType] = tb3RentPeriodStack(
        $admin,
        $customer,
        'UP-402',
        Carbon::parse('2025-08-01'),
        Carbon::parse('2025-11-30'),
    );

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/utilities/bulk-import/confirm', [
            'utility_type_id' => $utilityType->id,
            'rows' => [
                [
                    'building' => $room->building->building_name,
                    'room_number' => $room->room_number,
                    'billing_month' => '01/08/2025',
                    'reading_date' => '15/08/2025',
                    'current_reading' => 125,
                ],
                [
                    'building' => $room->building->building_name,
                    'room_number' => 'NO-ROOM',
                    'billing_month' => '01/08/2025',
                    'reading_date' => '15/08/2025',
                    'current_reading' => 125,
                ],
            ],
        ])
        ->assertStatus(422)
        ->assertJsonPath('data.rows.0', 'Please fix all invalid utility rows before importing.');

    expect(Utility::query()->count())->toBe(0);
});

