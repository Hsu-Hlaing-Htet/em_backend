<?php

namespace Database\Seeders;

use App\Models\UtilityType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class UtilityTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $types = [
            'Electricity',
            'Water',
            'Gas',
        ];

        foreach ($types as $name) {
            UtilityType::query()->updateOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'status' => UtilityType::STATUS_ACTIVE,
                ],
            );
        }
    }
}
