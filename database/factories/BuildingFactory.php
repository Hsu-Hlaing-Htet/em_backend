<?php

namespace Database\Factories;

use App\Models\Building;
use Database\Seeders\Support\MyanmarSampleData;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Building>
 */
class BuildingFactory extends Factory
{
    protected $model = Building::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        static $index = 0;
        $buildings = MyanmarSampleData::buildings();
        $building = $buildings[$index % count($buildings)];
        $index++;

        return [
            'building_name' => $building['building_name'].' '.fake()->unique()->numerify('##'),
            'location' => $building['location'],
            'description' => $building['description'],
        ];
    }
}
