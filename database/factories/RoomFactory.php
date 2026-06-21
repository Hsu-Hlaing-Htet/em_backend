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
        $type = fake()->randomElement([
            Room::TYPE_SALE,
            Room::TYPE_RENT,
            Room::TYPE_BOTH,
        ]);

        $salePrice = in_array(
            $type,
            [Room::TYPE_SALE, Room::TYPE_BOTH],
            true
        )
            ? fake()->numberBetween(80000000, 450000000)
            : 0;

        $rentPrice = in_array(
            $type,
            [Room::TYPE_RENT, Room::TYPE_BOTH],
            true
        )
            ? fake()->numberBetween(300000, 2500000)
            : 0;

        return [
            'building_id' => Building::factory(),

            'room_number' =>
                fake()->randomElement(['A', 'B', 'C', 'D'])
                . '-'
                . fake()->numberBetween(101, 2505),

            'floor_number' => fake()->numberBetween(1, 25),

            'area_sqft' => fake()->numberBetween(600, 3000),

            'description' => fake()->randomElement([
                'Modern condominium with city view and spacious living area.',
                'Luxury residence featuring premium finishes and natural lighting.',
                'Comfortable family unit located in a prime residential area.',
                'Well-designed apartment with balcony and modern amenities.',
                'Exclusive residence offering a blend of comfort and elegance.',
            ]),

            'type' => $type,

            'status' => fake()->randomElement([
                Room::STATUS_AVAILABLE,
                Room::STATUS_RESERVED,
                Room::STATUS_OCCUPIED,
                Room::STATUS_SOLD,
                Room::STATUS_MAINTENANCE,
            ]),

            'sale_price' => $salePrice,

            'rent_price' => $rentPrice,

            'rent_deposit_price' => $rentPrice > 0
                ? $rentPrice * 2
                : 0,

            'booking_deposit_price' => $rentPrice > 0
                ? $rentPrice
                : 0,
        ];
    }
}