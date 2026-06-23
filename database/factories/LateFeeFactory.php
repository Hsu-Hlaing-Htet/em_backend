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
        return [
            'name' => fake()->words(3, true),
            'type' => fake()->randomElement(LateFee::types()),
            'value' => fake()->randomFloat(2, 1, 10000),
            'per' => fake()->randomElement(LateFee::perOptions()),
            'grace_days' => fake()->numberBetween(0, 14),
            'status' => fake()->randomElement(LateFee::statuses()),
        ];
    }
}
