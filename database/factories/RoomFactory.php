<?php

namespace Database\Factories;

use App\Models\Building;
use App\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Room>
 */
class RoomFactory extends Factory
{
    protected $model = Room::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $salePrice = fake()->randomFloat(2, 500000000, 1500000000);
        $rentPrice = fake()->randomFloat(2, 300000, 2500000);

        return [
            'building_id' => Building::factory(),
            'room_number' => strtoupper(fake()->unique()->bothify('?-###')),
            'floor_number' => fake()->numberBetween(1, 20),
            'area_sqft' => fake()->randomFloat(2, 600, 2200),
            'description' => fake()->optional(0.6)->sentence(12),
            'type' => fake()->randomElement(['sale', 'rent', 'both']),
            'status' => 'available',
            'sale_price' => $salePrice,
            'rent_price' => $rentPrice,
            'rent_deposit_price' => round($rentPrice * 2, 2),
            'booking_deposit_price' => round($salePrice * 0.1, 2),
        ];
    }

    public function forSale(): static
    {
        return $this->state(fn () => [
            'type' => fake()->randomElement(['sale', 'both']),
            'status' => 'available',
        ]);
    }

    public function forRent(): static
    {
        return $this->state(fn () => [
            'type' => fake()->randomElement(['rent', 'both']),
            'status' => 'available',
        ]);
    }

    public function occupied(): static
    {
        return $this->state(fn () => ['status' => 'occupied']);
    }

    public function sold(): static
    {
        return $this->state(fn () => ['status' => 'sold']);
    }
}
