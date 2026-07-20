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
            'unit_price' => fake()->randomFloat(4, 50, 500),
            'effective_date' => fake()->dateTimeBetween('-1 year', 'now'),
            'status' => fake()->randomElement(['active', 'inactive']),
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => ['status' => 'active']);
    }
}
