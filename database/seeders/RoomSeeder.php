<?php

namespace Database\Seeders;

use App\Models\Building;
use App\Models\Room;
use Illuminate\Database\Seeder;

class RoomSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $buildings = Building::query()->orderBy('id')->get();

        if ($buildings->isEmpty()) {
            $this->command?->warn('No buildings found. Run BuildingSeeder first.');

            return;
        }

        $remaining = 200;
        $buildingCount = $buildings->count();

        foreach ($buildings as $index => $building) {
            $roomsForBuilding = (int) floor(200 / $buildingCount);
            $roomsForBuilding += $index < (200 % $buildingCount) ? 1 : 0;

            Room::factory()
                ->count($roomsForBuilding)
                ->create([
                    'building_id' => $building->id,
                ]);

            $remaining -= $roomsForBuilding;
        }

        if ($remaining > 0) {
            Room::factory()
                ->count($remaining)
                ->create([
                    'building_id' => $buildings->random()->id,
                ]);
        }
    }
}
