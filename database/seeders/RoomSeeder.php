<?php

namespace Database\Seeders;

use App\Models\Building;
use App\Models\Room;
use Database\Seeders\Support\SeedNumberGenerator;
use Illuminate\Database\Seeder;

class RoomSeeder extends Seeder
{
    /**
     * Curated rooms with realistic MMK pricing for end-to-end workflow scenarios.
     */
    public function run(): void
    {
        $buildings = Building::query()->orderBy('id')->get();

        if ($buildings->count() < 4) {
            $this->command?->warn('At least 4 buildings are required. Run BuildingSeeder first.');

            return;
        }

        $rooms = collect([
            ['type' => 'rent', 'status' => 'available', 'area' => 850, 'rent' => 450000, 'sale' => 0],
            ['type' => 'rent', 'status' => 'available', 'area' => 920, 'rent' => 520000, 'sale' => 0],
            ['type' => 'sale', 'status' => 'available', 'area' => 1100, 'rent' => 0, 'sale' => 185000000],
            ['type' => 'both', 'status' => 'available', 'area' => 980, 'rent' => 600000, 'sale' => 210000000],
            ['type' => 'sale', 'status' => 'available', 'area' => 1250, 'rent' => 0, 'sale' => 275000000],
            ['type' => 'sale', 'status' => 'available', 'area' => 1180, 'rent' => 0, 'sale' => 248000000],
            ['type' => 'rent', 'status' => 'available', 'area' => 1050, 'rent' => 650000, 'sale' => 0],
            ['type' => 'rent', 'status' => 'available', 'area' => 880, 'rent' => 480000, 'sale' => 0],
            ['type' => 'rent', 'status' => 'available', 'area' => 950, 'rent' => 550000, 'sale' => 0],
            ['type' => 'sale', 'status' => 'available', 'area' => 1400, 'rent' => 0, 'sale' => 320000000],
            ['type' => 'sale', 'status' => 'available', 'area' => 1320, 'rent' => 0, 'sale' => 295000000],
            ['type' => 'rent', 'status' => 'available', 'area' => 780, 'rent' => 420000, 'sale' => 0],
            ['type' => 'rent', 'status' => 'available', 'area' => 1000, 'rent' => 580000, 'sale' => 0],
            ['type' => 'both', 'status' => 'available', 'area' => 1150, 'rent' => 700000, 'sale' => 260000000],
            ['type' => 'sale', 'status' => 'available', 'area' => 1080, 'rent' => 0, 'sale' => 230000000],
            ['type' => 'rent', 'status' => 'available', 'area' => 1200, 'rent' => 750000, 'sale' => 0],
            ['type' => 'rent', 'status' => 'available', 'area' => 860, 'rent' => 490000, 'sale' => 0],
            ['type' => 'sale', 'status' => 'available', 'area' => 990, 'rent' => 0, 'sale' => 198000000],
        ])->map(function (array $room, int $index) use ($buildings): array {
            $buildingIndex = $index % min(4, $buildings->count());
            $roomNumber = SeedNumberGenerator::roomNumberForIndex($index, $buildingIndex, 4);
            $unit = (int) substr($roomNumber, strpos($roomNumber, '-') + 1);

            return [
                ...$room,
                'building' => $buildingIndex,
                'room_number' => $roomNumber,
                'floor' => max(1, intdiv($unit, 100)),
            ];
        })->all();

        foreach ($rooms as $room) {
            /** @var Building $building */
            $building = $buildings[$room['building']];
            $rent = (float) $room['rent'];
            $sale = (float) $room['sale'];

            Room::query()->create([
                'building_id' => $building->id,
                'room_number' => $room['room_number'],
                'floor_number' => $room['floor'],
                'width_ft' => round(sqrt($room['area']) * 0.9, 2),
                'length_ft' => round(sqrt($room['area']) * 1.1, 2),
                'area_sqft' => $room['area'],
                'description' => sprintf(
                    '%s unit on floor %d of %s with natural light and city views.',
                    ucfirst($room['type'] === 'both' ? 'flexible' : $room['type']),
                    $room['floor'],
                    $building->building_name,
                ),
                'type' => $room['type'],
                'status' => $room['status'],
                'sale_price' => $sale,
                'rent_price' => $rent,
                'rent_deposit_price' => $rent > 0 ? round($rent * 2, 2) : 0,
                'booking_deposit_price' => $sale > 0 ? round($sale * 0.1, 2) : 0,
            ]);
        }

        $this->command?->info('Seeded '.count($rooms).' curated rooms with MMK pricing.');
    }
}
