<?php

namespace Database\Seeders;

use App\Models\Building;
use Database\Seeders\Support\MyanmarSampleData;
use Illuminate\Database\Seeder;

class BuildingSeeder extends Seeder
{
    public function run(): void
    {
        $buildings = array_slice(MyanmarSampleData::buildings(), 0, 6);

        foreach ($buildings as $building) {
            Building::query()->create($building);
        }
    }
}
