<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

test('utility list filters by billing month date range', function () {
    $admin = tb3Admin();
    $customer = User::query()->where('email', 'mgmg@gmail.com')->firstOrFail();
    ['room' => $room, 'utilityType' => $utilityType, 'rate' => $rate] = tb3RentPeriodStack(
        $admin,
        $customer,
        'UP-501',
        Carbon::parse('2025-08-01'),
        Carbon::parse('2025-12-31'),
    );
    ['room' => $otherRoom, 'utilityType' => $otherUtilityType, 'rate' => $otherRate] = tb3RentPeriodStack(
        $admin,
        $customer,
        'UP-502',
        Carbon::parse('2025-08-01'),
        Carbon::parse('2025-12-31'),
    );

    foreach ([
        ['billing_month' => '2025-08-01', 'reading_date' => '2025-08-15', 'previous' => 0, 'current' => 50],
        ['billing_month' => '2025-09-01', 'reading_date' => '2025-09-15', 'previous' => 50, 'current' => 80],
        ['billing_month' => '2025-10-01', 'reading_date' => '2025-10-15', 'previous' => 80, 'current' => 120],
    ] as $period) {
        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/utilities', [
                'room_id' => $room->id,
                'billing_month' => $period['billing_month'],
                'reading_date' => $period['reading_date'],
                'utility_items' => [[
                    'utility_type_id' => $utilityType->id,
                    'previous_reading' => $period['previous'],
                    'current_reading' => $period['current'],
                    'unit_price' => (float) $rate->unit_price,
                ]],
            ])
            ->assertCreated();
    }

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/utilities', [
            'room_id' => $otherRoom->id,
            'billing_month' => '2025-08-01',
            'reading_date' => '2025-08-15',
            'utility_items' => [[
                'utility_type_id' => $otherUtilityType->id,
                'previous_reading' => 0,
                'current_reading' => 75,
                'unit_price' => (float) $otherRate->unit_price,
            ]],
        ])
        ->assertCreated();

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/utilities?billing_month_from=2025-09-01&billing_month_to=2025-10-01')
        ->assertOk()
        ->assertJsonPath('data.total', 2);

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/utilities?billing_month_from=2025-10-01')
        ->assertOk()
        ->assertJsonPath('data.total', 1);

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/utilities?billing_month_to=2025-08-01')
        ->assertOk()
        ->assertJsonPath('data.total', 2);

    $this->actingAs($admin, 'sanctum')
        ->getJson("/api/utilities?room_id={$room->id}")
        ->assertOk()
        ->assertJsonPath('data.total', 3);

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/utilities?search=UP-501')
        ->assertOk()
        ->assertJsonPath('data.total', 3);
});

test('utility list supports pending status and customer email search', function () {
    $admin = tb3Admin();
    $customer = tb3Customer();
    ['room' => $room, 'contract' => $contract] = tb3ActiveContract($admin, $customer, 'TB3-SRCH');
    $utilityType = \App\Models\UtilityType::query()->where('slug', 'electricity')->firstOrFail();
    $rate = \App\Models\UtilityRate::query()->where('utility_type_id', $utilityType->id)->where('status', 'active')->firstOrFail();
    $billingMonth = Carbon::parse($contract->start_date)->startOfMonth()->toDateString();

    $utilityId = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/utilities', [
            'room_id' => $room->id,
            'billing_month' => $billingMonth,
            'utility_items' => [[
                'utility_type_id' => $utilityType->id,
                'previous_reading' => 100,
                'current_reading' => 150,
                'unit_price' => (float) $rate->unit_price,
            ]],
        ])
        ->assertCreated()
        ->json('data.id');

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/utilities/{$utilityId}/submit")
        ->assertOk();

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/utilities?status=pending&search='.$customer->email)
        ->assertOk()
        ->assertJsonPath('data.total', 1)
        ->assertJsonPath('data.data.0.id', $utilityId);
});
