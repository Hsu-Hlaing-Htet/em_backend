<?php

namespace Database\Seeders;

use App\Models\Building;
use App\Models\Room;
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

        $rooms = [
            // Available rentals / sales (no active contract)
            ['building' => 0, 'room_number' => 'A-101', 'floor' => 1, 'type' => 'rent', 'status' => 'available', 'area' => 850, 'rent' => 450000, 'sale' => 0],
            ['building' => 0, 'room_number' => 'A-102', 'floor' => 1, 'type' => 'rent', 'status' => 'available', 'area' => 920, 'rent' => 520000, 'sale' => 0],
            ['building' => 1, 'room_number' => 'B-201', 'floor' => 2, 'type' => 'sale', 'status' => 'available', 'area' => 1100, 'rent' => 0, 'sale' => 185000000],
            ['building' => 1, 'room_number' => 'B-202', 'floor' => 2, 'type' => 'both', 'status' => 'available', 'area' => 980, 'rent' => 600000, 'sale' => 210000000],

            // Reserved (sale pending / approved contracts assigned later)
            ['building' => 2, 'room_number' => 'C-301', 'floor' => 3, 'type' => 'sale', 'status' => 'available', 'area' => 1250, 'rent' => 0, 'sale' => 275000000],
            ['building' => 2, 'room_number' => 'C-302', 'floor' => 3, 'type' => 'sale', 'status' => 'available', 'area' => 1180, 'rent' => 0, 'sale' => 248000000],

            // Occupied rentals (active contracts)
            ['building' => 0, 'room_number' => 'A-501', 'floor' => 5, 'type' => 'rent', 'status' => 'available', 'area' => 1050, 'rent' => 650000, 'sale' => 0],
            ['building' => 3, 'room_number' => 'D-402', 'floor' => 4, 'type' => 'rent', 'status' => 'available', 'area' => 880, 'rent' => 480000, 'sale' => 0],
            ['building' => 3, 'room_number' => 'D-403', 'floor' => 4, 'type' => 'rent', 'status' => 'available', 'area' => 950, 'rent' => 550000, 'sale' => 0],

            // Sold / completed sale inventory (start available; contract seeder marks sold)
            ['building' => 4, 'room_number' => 'E-701', 'floor' => 7, 'type' => 'sale', 'status' => 'available', 'area' => 1400, 'rent' => 0, 'sale' => 320000000],
            ['building' => 4, 'room_number' => 'E-702', 'floor' => 7, 'type' => 'sale', 'status' => 'available', 'area' => 1320, 'rent' => 0, 'sale' => 295000000],

            // Extra pipeline / listing inventory
            ['building' => 1, 'room_number' => 'B-305', 'floor' => 3, 'type' => 'rent', 'status' => 'available', 'area' => 780, 'rent' => 420000, 'sale' => 0],
            ['building' => 2, 'room_number' => 'C-501', 'floor' => 5, 'type' => 'rent', 'status' => 'available', 'area' => 1000, 'rent' => 580000, 'sale' => 0],
            ['building' => 5, 'room_number' => 'F-101', 'floor' => 1, 'type' => 'both', 'status' => 'available', 'area' => 1150, 'rent' => 700000, 'sale' => 260000000],
            ['building' => 5, 'room_number' => 'F-102', 'floor' => 1, 'type' => 'sale', 'status' => 'available', 'area' => 1080, 'rent' => 0, 'sale' => 230000000],
            ['building' => 0, 'room_number' => 'A-801', 'floor' => 8, 'type' => 'rent', 'status' => 'available', 'area' => 1200, 'rent' => 750000, 'sale' => 0],
            ['building' => 3, 'room_number' => 'D-601', 'floor' => 6, 'type' => 'rent', 'status' => 'available', 'area' => 860, 'rent' => 490000, 'sale' => 0],
            ['building' => 4, 'room_number' => 'E-203', 'floor' => 2, 'type' => 'sale', 'status' => 'available', 'area' => 990, 'rent' => 0, 'sale' => 198000000],
        ];

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
