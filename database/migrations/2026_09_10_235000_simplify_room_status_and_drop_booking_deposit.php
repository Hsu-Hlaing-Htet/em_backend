<?php

use App\Models\Contract;
use App\Models\Room;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Migrate legacy reserved rooms onto Available / Occupied / Sold.
        $reservedRooms = DB::table('rooms')->where('status', 'reserved')->get(['id']);

        foreach ($reservedRooms as $room) {
            $activeSale = DB::table('contracts')
                ->where('room_id', $room->id)
                ->where('type', 'sale')
                ->whereIn('status', [Contract::STATUS_ACTIVE, Contract::STATUS_COMPLETED])
                ->exists();

            $activeRent = DB::table('contracts')
                ->where('room_id', $room->id)
                ->where('type', 'rent')
                ->where('status', Contract::STATUS_ACTIVE)
                ->exists();

            $nextStatus = Room::STATUS_AVAILABLE;

            if ($activeSale) {
                $nextStatus = Room::STATUS_SOLD;
            } elseif ($activeRent) {
                $nextStatus = Room::STATUS_OCCUPIED;
            }

            DB::table('rooms')->where('id', $room->id)->update(['status' => $nextStatus]);
        }

        // Maintenance is no longer a room business status.
        DB::table('rooms')
            ->where('status', 'maintenance')
            ->update(['status' => Room::STATUS_AVAILABLE]);

        if (Schema::hasColumn('rooms', 'booking_deposit_price')) {
            Schema::table('rooms', function (Blueprint $table): void {
                $table->dropColumn('booking_deposit_price');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('rooms', 'booking_deposit_price')) {
            Schema::table('rooms', function (Blueprint $table): void {
                $table->decimal('booking_deposit_price', 12, 2)->default(0)->after('rent_deposit_price');
            });
        }
    }
};
