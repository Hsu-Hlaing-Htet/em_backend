<?php

use App\Models\Utility;
use App\Models\UtilityItem;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('seeded utility records follow monthly sequence and leave august 2026 importable', function () {
    $this->seed(DatabaseSeeder::class);

    expect(Utility::query()->whereDate('billing_month', '2026-08-01')->count())->toBe(0);

    $duplicates = UtilityItem::query()
        ->join('utilities', 'utilities.id', '=', 'utility_items.utility_id')
        ->selectRaw('utilities.room_id, utility_items.utility_type_id, date(utilities.billing_month) as billing_month, count(*) as records')
        ->groupBy('utilities.room_id', 'utility_items.utility_type_id', 'billing_month')
        ->havingRaw('count(*) > 1')
        ->get()
        ->map(fn ($row) => [
            'room_id' => (int) $row->room_id,
            'utility_type_id' => (int) $row->utility_type_id,
            'billing_month' => $row->billing_month,
            'records' => (int) $row->records,
        ])
        ->values()
        ->all();

    expect($duplicates)->toBe([]);

    $itemsByRoom = UtilityItem::query()
        ->with('utility')
        ->whereHas('utility')
        ->get()
        ->groupBy(fn (UtilityItem $item) => $item->utility->room_id.'|'.$item->utility_type_id);

    foreach ($itemsByRoom as $history) {
        $ordered = $history
            ->sortBy(fn (UtilityItem $item) => sprintf(
                '%s-%010d',
                $item->utility->billing_month->toDateString(),
                $item->utility->id,
            ))
            ->values();
        $seenMonths = [];
        $lastReading = null;

        foreach ($ordered as $item) {
            $monthKey = $item->utility->billing_month->toDateString();
            expect($seenMonths)->not->toHaveKey($monthKey, sprintf(
                'Duplicate utility record found for room %s utility type %s billing month %s.',
                $item->utility->room_id,
                $item->utility_type_id,
                $monthKey,
            ));
            $seenMonths[$monthKey] = true;

            if ($lastReading !== null) {
                expect((float) $item->previous_reading)->toBe(
                    (float) $lastReading,
                    sprintf(
                        'Meter reading gap found for room %s utility type %s at %s.',
                    $item->utility->room_id,
                    $item->utility_type_id,
                        $item->utility->billing_month->toDateString(),
                    ),
                );
            }

            $lastReading = $item->current_reading;
        }
    }
});
