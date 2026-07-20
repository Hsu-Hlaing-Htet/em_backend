<?php

namespace Database\Seeders;

use App\Models\Building;
use Database\Seeders\Support\MyanmarSampleData;
use Illuminate\Database\Seeder;

class BuildingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (MyanmarSampleData::buildings() as $building) {
            Building::query()->create($building);
        }
    }
}
