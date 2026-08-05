<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            $table->string('approval_status')->default('pending')->after('status');
            $table->timestamp('approved_at')->nullable()->after('approved_by');
        });

        DB::table('receipts')->where('status', 'issued')->update([
            'approval_status' => 'approved',
            'approved_at' => DB::raw('COALESCE(issued_at, updated_at, created_at)'),
        ]);

        DB::table('receipts')->where('status', '!=', 'issued')->update([
            'approval_status' => 'pending',
            'approved_at' => null,
            'approved_by' => null,
        ]);
    }

    public function down(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            $table->dropColumn(['approval_status', 'approved_at']);
        });
    }
};
