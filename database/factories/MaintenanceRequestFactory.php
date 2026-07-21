<?php

namespace Database\Factories;

use App\Models\MaintenanceRequest;
use App\Models\Room;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaintenanceRequest>
 */
class MaintenanceRequestFactory extends Factory
{
    protected $model = MaintenanceRequest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'room_id' => Room::factory()->occupied(),
            'user_id' => User::factory()->customer(),
            'created_by' => User::factory()->customer(),
            'approved_by' => null,
            'approved_at' => null,
            'title' => fake()->sentence(4),
            'description' => fake()->optional(0.8)->paragraph(),
            'status' => fake()->randomElement(['pending', 'in_progress', 'completed', 'rejected']),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => [
            'status' => 'pending',
            'approved_by' => null,
            'approved_at' => null,
        ]);
    }
}
