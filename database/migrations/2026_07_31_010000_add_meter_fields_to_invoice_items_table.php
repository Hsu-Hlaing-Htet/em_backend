<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->decimal('previous_reading', 14, 2)->nullable()->after('description');
            $table->decimal('current_reading', 14, 2)->nullable()->after('previous_reading');
            $table->decimal('usage', 14, 2)->nullable()->after('current_reading');
            $table->decimal('unit_price', 14, 2)->nullable()->after('usage');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropColumn(['previous_reading', 'current_reading', 'usage', 'unit_price']);
        });
    }
};
