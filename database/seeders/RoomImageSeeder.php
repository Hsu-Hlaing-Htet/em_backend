<?php

namespace Database\Seeders;

use App\Models\Room;
use App\Models\RoomImage;
use App\Services\RoomImageService;
use Database\Seeders\Support\PlaceholderImage;
use Illuminate\Database\Seeder;

class RoomImageSeeder extends Seeder
{
    public function run(): void
    {
        $roomImageService = app(RoomImageService::class);
        $rooms = Room::query()->with(['building', 'roomImages'])->get();

        foreach ($rooms as $room) {
            foreach ($room->roomImages as $existingImage) {
                $roomImageService->delete($existingImage);
            }

            $buildingName = $room->building?->building_name ?? 'Unknown Building';
            $basePath = 'buildings/'.$buildingName.'/'.$room->room_number;

            $primaryPath = PlaceholderImage::store($basePath.'/primary.jpg');
            $galleryPath = PlaceholderImage::store($basePath.'/gallery.jpg');

            RoomImage::query()->create([
                'room_id' => $room->id,
                'image_path' => $primaryPath,
                'description' => 'Living Room',
                'is_primary' => true,
                'sort_order' => 0,
            ]);

            RoomImage::query()->create([
                'room_id' => $room->id,
                'image_path' => $galleryPath,
                'description' => 'Master Bedroom',
                'is_primary' => false,
                'sort_order' => 1,
            ]);
        }

        $this->command?->info('Seeded room image files: '.$rooms->count() * 2);
    }
}
