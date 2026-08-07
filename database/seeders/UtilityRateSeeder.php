<?php

namespace Database\Seeders;

use App\Models\UtilityRate;
use App\Models\UtilityType;
use Illuminate\Database\Seeder;

class UtilityRateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $rates = [
            'electricity' => 125.5000,
            'water' => 85.0000,
            'gas' => 210.7500,
            'internet' => 35000.0000,
        ];

        foreach ($rates as $slug => $unitPrice) {
            $utilityType = UtilityType::query()->where('slug', $slug)->first();

            if (! $utilityType) {
                continue;
            }

            UtilityRate::query()->updateOrCreate(
                [
                    'utility_type_id' => $utilityType->id,
                    'effective_date' => now()->subMonths(3)->toDateString(),
                ],
                [
                    'unit_price' => $unitPrice,
                    'status' => 'active',
                ]
            );

            UtilityRate::query()->updateOrCreate(
                [
                    'utility_type_id' => $utilityType->id,
                    'effective_date' => now()->subYear()->toDateString(),
                ],
                [
                    'unit_price' => round($unitPrice * 0.95, 4),
                    'status' => 'inactive',
                ]
            );
        }
    }
}
