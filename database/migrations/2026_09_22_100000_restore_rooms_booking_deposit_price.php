<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('rooms', 'booking_deposit_price')) {
            Schema::table('rooms', function (Blueprint $table): void {
                $table->decimal('booking_deposit_price', 12, 2)->default(0)->after('rent_deposit_price');
            });
        }

        // Backfill sale rooms that still have a zero deposit using 10% of sale price
        // (matches demo seeders) so contract drafts can derive a non-null deposit.
        DB::table('rooms')
            ->where('booking_deposit_price', 0)
            ->where('sale_price', '>', 0)
            ->whereIn('type', ['sale', 'both'])
            ->update([
                'booking_deposit_price' => DB::raw('ROUND(sale_price * 0.1, 2)'),
            ]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('rooms', 'booking_deposit_price')) {
            Schema::table('rooms', function (Blueprint $table): void {
                $table->dropColumn('booking_deposit_price');
            });
        }
    }
};
