<?php

namespace Database\Factories;

use App\Models\Building;
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
        $names = [
            'Rosewood Residences',
            'Royale Court',
            'Emerald Plaza',
            'Sapphire Heights',
            'Golden Lotus Tower',
        ];

        $cities = ['Yangon', 'Mandalay', 'Naypyidaw', 'Bago', 'Taunggyi'];

        return [
            'building_name' => fake()->randomElement($names).' '.fake()->unique()->numberBetween(1, 9),
            'location' => fake()->randomElement($cities).', Myanmar',
            'description' => fake()->paragraph(),
        ];
    }
}
