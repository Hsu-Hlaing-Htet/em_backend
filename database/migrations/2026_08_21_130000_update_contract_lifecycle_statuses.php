<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table): void {
            $table->date('termination_date')->nullable()->after('status');
            $table->text('termination_reason')->nullable()->after('termination_date');
        });

        DB::table('contracts')
            ->where('status', 'draft')
            ->update(['status' => 'pending']);

        DB::table('contracts')
            ->where('status', 'approved')
            ->update(['status' => 'active']);

        DB::table('contracts')
            ->where('status', 'cancelled')
            ->update(['status' => 'terminated']);
    }

    public function down(): void
    {
        DB::table('contracts')
            ->where('status', 'pending')
            ->update(['status' => 'draft']);

        DB::table('contracts')
            ->where('type', 'sale')
            ->where('status', 'active')
            ->update(['status' => 'approved']);

        DB::table('contracts')
            ->where('status', 'terminated')
            ->update(['status' => 'cancelled']);

        Schema::table('contracts', function (Blueprint $table): void {
            $table->dropColumn(['termination_date', 'termination_reason']);
        });
    }
};
