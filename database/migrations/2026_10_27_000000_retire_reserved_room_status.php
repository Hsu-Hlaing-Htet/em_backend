<?php

use App\Models\Contract;
use App\Models\Room;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('rooms')->where('status', 'reserved')->orderBy('id')->each(function (object $room): void {
            $hasCompletedOrActiveSale = DB::table('contracts')
                ->where('room_id', $room->id)
                ->where('type', 'sale')
                ->whereIn('status', [Contract::STATUS_ACTIVE, Contract::STATUS_COMPLETED])
                ->exists();

            $hasActiveRent = DB::table('contracts')
                ->where('room_id', $room->id)
                ->where('type', 'rent')
                ->where('status', Contract::STATUS_ACTIVE)
                ->exists();

            DB::table('rooms')->where('id', $room->id)->update([
                'status' => $hasCompletedOrActiveSale
                    ? Room::STATUS_SOLD
                    : ($hasActiveRent ? Room::STATUS_OCCUPIED : Room::STATUS_AVAILABLE),
            ]);
        });
    }

    public function down(): void
    {
        // A prior room status cannot be inferred safely from contract and financial history.
    }
};
