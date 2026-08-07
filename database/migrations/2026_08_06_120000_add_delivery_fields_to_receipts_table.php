<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('receipts', 'sent_at')) {
            Schema::table('receipts', function (Blueprint $table) {
                $table->timestamp('sent_at')->nullable()->after('issued_at');
            });
        }

        if (! Schema::hasColumn('receipts', 'sent_by')) {
            Schema::table('receipts', function (Blueprint $table) {
                $table->foreignId('sent_by')->nullable()->after('sent_at')->constrained('users')->nullOnDelete();
            });
        }

        DB::table('receipts')
            ->where('status', 'issued')
            ->whereNull('sent_at')
            ->update([
                'sent_at' => DB::raw('COALESCE(issued_at, updated_at, created_at)'),
                'sent_by' => DB::raw('COALESCE(sent_by, approved_by, created_by)'),
            ]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('receipts', 'sent_by')) {
            Schema::table('receipts', function (Blueprint $table) {
                $table->dropConstrainedForeignId('sent_by');
            });
        }

        if (Schema::hasColumn('receipts', 'sent_at')) {
            Schema::table('receipts', function (Blueprint $table) {
                $table->dropColumn('sent_at');
            });
        }
    }
};
