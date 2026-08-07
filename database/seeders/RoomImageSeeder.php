<?php

namespace Database\Seeders;

use App\Models\Room;
use Database\Seeders\Support\RoomImageSeederSupport;
use Illuminate\Database\Seeder;

/**
 * Ensures every room has 2–4 valid local gallery images at deterministic paths.
 * Idempotent: re-running updates canonical demo paths without duplicating rows.
 */
class RoomImageSeeder extends Seeder
{
    public function run(): void
    {
        $filled = RoomImageSeederSupport::seedAllRooms();

        $this->command?->info("RoomImageSeeder ensured canonical images for all rooms ({$filled} image rows upserted).");
    }
}
