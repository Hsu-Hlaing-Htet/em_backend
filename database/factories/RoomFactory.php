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

        $width = fake()->randomFloat(2, 15, 50);
        $length = fake()->randomFloat(2, 15, 50);

        return [
            'building_id' => Building::factory(),
            'room_number' => strtoupper(fake()->unique()->bothify('?-###')),
            'floor_number' => fake()->numberBetween(1, 20),
            'width_ft' => $width,
            'length_ft' => $length,
            'area_sqft' => round($width * $length, 2),
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
