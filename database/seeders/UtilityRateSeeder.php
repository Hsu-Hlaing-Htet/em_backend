<?php

namespace Database\Seeders;

use App\Models\UtilityRate;
use App\Models\UtilityType;
use Illuminate\Database\Seeder;

class UtilityRateSeeder extends Seeder
{
    public function run(): void
    {
        $rates = [
            ['name' => 'Water', 'price' => 250],
            ['name' => 'Electricity', 'price' => 500],
            ['name' => 'Gas', 'price' => 800],
            ['name' => 'Internet', 'price' => 30000],
            ['name' => 'Maintenance', 'price' => 10000],
        ];

        foreach ($rates as $rate) {
            $type = UtilityType::where('name', $rate['name'])->first();

            if ($type) {
                UtilityRate::create([
                    'utility_type_id' => $type->id,
                    'unit_price' => $rate['price'],
                    'effective_date' => now()->toDateString(),
                    'status' => 'active',
                ]);
            }
        }
    }
}