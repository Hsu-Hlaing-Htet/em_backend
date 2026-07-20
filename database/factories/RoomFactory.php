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
        $salePrice = fake()->randomElement([
            450000000,   // 450 Million MMK
            650000000,   // 650 Million MMK
            850000000,   // 850 Million MMK
            1200000000,  // 1.2 Billion MMK
            1800000000,  // 1.8 Billion MMK
        ]);
    
        $rentPrice = fake()->randomElement([
            500000,   // 500K MMK/month
            800000,   // 800K MMK/month
            1200000,  // 1.2M MMK/month
            1800000,  // 1.8M MMK/month
            2500000,  // 2.5M MMK/month
        ]);
    
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
            'rent_deposit_price' => $rentPrice * 2,
            'booking_deposit_price' => $salePrice * 0.1,
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
