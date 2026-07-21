<?php

namespace Database\Factories;

use App\Models\LateFee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LateFee>
 */
class LateFeeFactory extends Factory
{
    protected $model = LateFee::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = fake()->randomElement(['fixed', 'percentage']);

        return [
            'name' => fake()->words(3, true),
            'type' => $type,
            'value' => $type === 'percentage'
                ? fake()->randomFloat(2, 1, 10)
                : fake()->randomFloat(2, 5000, 50000),
            'per' => fake()->randomElement(['day', 'month']),
            'grace_days' => fake()->numberBetween(0, 7),
            'status' => fake()->randomElement(['active', 'inactive']),
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => ['status' => 'active']);
    }
}
