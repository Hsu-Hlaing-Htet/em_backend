<?php

namespace Database\Factories;

use App\Models\UtilityRate;
use App\Models\UtilityType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UtilityRate>
 */
class UtilityRateFactory extends Factory
{
    protected $model = UtilityRate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'utility_type_id' => UtilityType::factory(),
            'unit_price' => fake()->randomFloat(2, 100, 5000),
            'effective_date' => fake()->date(),
            'status' => fake()->randomElement(UtilityRate::statuses()),
        ];
    }
}
