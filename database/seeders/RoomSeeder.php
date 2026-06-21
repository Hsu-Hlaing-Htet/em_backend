<?php

namespace Database\Seeders;

use App\Models\Building;
use App\Models\Room;
use Illuminate\Database\Seeder;

class RoomSeeder extends Seeder
{
    public function run(): void
    {
        $buildings = Building::query()->get();

        if ($buildings->isEmpty()) {
            return;
        }

        foreach ($buildings as $building) {
            Room::query()->updateOrCreate(
                [
                    'building_id' => $building->id,
                    'room_number' => 'A-'.$building->id.'01',
                ],
                [
                    'floor_number' => 1,
                    'area_sqft' => 850.50,
                    'description' => 'Corner unit with natural light.',
                    'type' => Room::TYPE_RENT,
                    'status' => Room::STATUS_AVAILABLE,
                    'sale_price' => 0,
                    'rent_price' => 1200,
                    'rent_deposit_price' => 1200,
                    'booking_deposit_price' => 500,
                ],
            );

            Room::query()->updateOrCreate(
                [
                    'building_id' => $building->id,
                    'room_number' => 'B-'.$building->id.'02',
                ],
                [
                    'floor_number' => 2,
                    'area_sqft' => 1100.75,
                    'description' => 'Premium suite with balcony access.',
                    'type' => Room::TYPE_BOTH,
                    'status' => Room::STATUS_AVAILABLE,
                    'sale_price' => 250000,
                    'rent_price' => 1800,
                    'rent_deposit_price' => 1800,
                    'booking_deposit_price' => 1000,
                ],
            );
        }
    }
}
