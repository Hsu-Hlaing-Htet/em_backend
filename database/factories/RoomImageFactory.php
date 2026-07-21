<?php

namespace Database\Factories;

use App\Models\Room;
use App\Models\RoomImage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RoomImage>
 */
class RoomImageFactory extends Factory
{
    protected $model = RoomImage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'room_id' => Room::factory(),
            'image_path' => 'room-images/'.fake()->uuid().'.jpg',
            'description' => fake()->randomElement([
                'Living Room',
                'Master Bedroom',
                'Kitchen',
                'Bathroom',
                'Balcony',
            ]),
            'is_primary' => false,
            'sort_order' => fake()->numberBetween(0, 5),
        ];
    }

    public function primary(): static
    {
        return $this->state(fn () => [
            'is_primary' => true,
            'sort_order' => 0,
        ]);
    }
}
