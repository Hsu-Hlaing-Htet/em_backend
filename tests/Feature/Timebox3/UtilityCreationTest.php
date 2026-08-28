<?php

use App\Models\Utility;
use App\Models\UtilityItem;
use App\Models\UtilityRate;
use App\Models\UtilityType;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('meter reading creation calculates usage and rejects invalid readings', function () {
    $admin = tb3Admin();
    $customer = tb3Customer();
    ['room' => $room, 'contract' => $contract] = tb3ActiveContract($admin, $customer, 'TB3-MR');
    $utilityType = UtilityType::query()->where('slug', 'electricity')->firstOrFail();
    $rate = UtilityRate::query()->where('utility_type_id', $utilityType->id)->where('status', 'active')->firstOrFail();
    $firstBillingMonth = \Illuminate\Support\Carbon::parse($contract->start_date)->startOfMonth()->toDateString();

    $create = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/utilities', [
            'room_id' => $room->id,
            'billing_month' => $firstBillingMonth,
            'utility_items' => [[
                'utility_type_id' => $utilityType->id,
                'previous_reading' => 1000,
                'current_reading' => 1125,
                'unit_price' => (float) $rate->unit_price,
            ]],
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.contract_id', $contract->id);

    $utilityId = $create->json('data.id');
    $item = UtilityItem::query()->where('utility_id', $utilityId)->firstOrFail();
    expect((float) $item->usage)->toBe(125.0);
    expect((float) $item->amount)->toBe(round(125 * (float) $rate->unit_price, 2));
    expect($item->utility_id)->toBe($utilityId);
    expect(Utility::query()->find($utilityId)?->room_id)->toBe($room->id);

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/utilities', [
            'room_id' => $room->id,
            'billing_month' => \Illuminate\Support\Carbon::parse($firstBillingMonth)->addMonth()->toDateString(),
            'utility_items' => [[
                'utility_type_id' => $utilityType->id,
                'previous_reading' => 2000,
                'current_reading' => 1500,
                'unit_price' => (float) $rate->unit_price,
            ]],
        ])
        ->assertStatus(422);
});
