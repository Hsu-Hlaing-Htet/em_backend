<?php

namespace Database\Factories;

use App\Models\Room;
use App\Models\RoomImage;
use Database\Seeders\Support\PlaceholderImage;
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
            'image_path' => 'buildings/placeholder/room/primary.jpg',
            'description' => fake()->randomElement([
                'Living Room',
                'Master Bedroom',
                'Bedroom',
                'Kitchen',
                'Bathroom',
                'Balcony',
                'Parking Area',
                'Building Exterior',
            ]),
            'is_primary' => false,
            'sort_order' => fake()->numberBetween(0, 10),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (RoomImage $roomImage): void {
            $roomImage->loadMissing('room.building');

            $buildingName = $roomImage->room?->building?->building_name ?? 'Unknown Building';
            $roomNumber = $roomImage->room?->room_number ?? 'Unknown Room';
            $filename = $roomImage->is_primary ? 'primary.jpg' : 'gallery.jpg';
            $relativePath = PlaceholderImage::store('buildings/'.$buildingName.'/'.$roomNumber.'/'.$filename);

            if ($roomImage->image_path !== $relativePath) {
                $roomImage->updateQuietly(['image_path' => $relativePath]);
            }
        });
    }

    public function primary(): static
    {
        return $this->state(fn () => [
            'is_primary' => true,
            'sort_order' => 0,
        ]);
    }
}
