<?php

namespace Database\Seeders;

use App\Models\UtilityType;
use Illuminate\Database\Seeder;

class UtilityTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $types = [
            ['name' => 'Electricity', 'slug' => 'electricity', 'status' => 'active'],
            ['name' => 'Water', 'slug' => 'water', 'status' => 'active'],
            ['name' => 'Gas', 'slug' => 'gas', 'status' => 'active'],
            ['name' => 'Internet', 'slug' => 'internet', 'status' => 'active'],
            ['name' => 'Generator Fuel', 'slug' => 'generator-fuel', 'status' => 'inactive'],
        ];

        foreach ($types as $type) {
            UtilityType::query()->updateOrCreate(
                ['slug' => $type['slug']],
                [
                    'name' => $type['name'],
                    'status' => $type['status'],
                ]
            );
        }
    }
}
