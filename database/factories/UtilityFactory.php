<?php

namespace Database\Factories;

use App\Models\Room;
use App\Models\Utility;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Utility>
 */
class UtilityFactory extends Factory
{
    protected $model = Utility::class;
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'room_id' => Room::factory()->occupied(),
            'billing_month' => fake()->dateTimeBetween('-6 months', 'now')->format('Y-m-01'),
            'total_amount' => 0,
            'status' => fake()->randomElement(['draft', 'pending', 'approved', 'rejected']),
            'created_by' => User::factory()->admin(),
            'approved_by' => null,
            'approved_at' => null,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => [
            'status' => 'draft',
            'approved_by' => null,
            'approved_at' => null,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn () => [
            'status' => 'pending',
            'approved_by' => null,
            'approved_at' => null,
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => 'approved',
            'approved_by' => User::factory()->admin(),
            'approved_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => [
            'status' => 'rejected',
            'approved_by' => User::factory()->admin(),
            'approved_at' => now(),
        ]);
    }
}
