<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            Schema::hasTable('maintenance_requests')
            && ! Schema::hasColumn('maintenance_requests', 'maintenance_fee_amount')
        ) {
            Schema::table('maintenance_requests', function (Blueprint $table) {
                $table->decimal('maintenance_fee_amount', 14, 2)->nullable()->after('resolution_note');
                $table->text('charge_description')->nullable()->after('maintenance_fee_amount');
                $table->foreignId('invoice_id')
                    ->nullable()
                    ->after('charge_description')
                    ->constrained('invoices')
                    ->nullOnDelete();
            });
        }

        if (
            Schema::hasTable('invoice_items')
            && ! Schema::hasColumn('invoice_items', 'maintenance_request_id')
        ) {
            Schema::table('invoice_items', function (Blueprint $table) {
                $table->foreignId('maintenance_request_id')
                    ->nullable()
                    ->after('charge_type_id')
                    ->constrained('maintenance_requests')
                    ->nullOnDelete();
                $table->unique('maintenance_request_id');
            });
        }
    }

    public function down(): void
    {
        if (
            Schema::hasTable('invoice_items')
            && Schema::hasColumn('invoice_items', 'maintenance_request_id')
        ) {
            Schema::table('invoice_items', function (Blueprint $table) {
                $table->dropUnique(['maintenance_request_id']);
                $table->dropConstrainedForeignId('maintenance_request_id');
            });
        }

        if (
            Schema::hasTable('maintenance_requests')
            && Schema::hasColumn('maintenance_requests', 'maintenance_fee_amount')
        ) {
            Schema::table('maintenance_requests', function (Blueprint $table) {
                $table->dropConstrainedForeignId('invoice_id');
                $table->dropColumn(['maintenance_fee_amount', 'charge_description']);
            });
        }
    }
};
