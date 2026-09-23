<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Restores the maintenance_categories source-of-truth table and optional FK
 * on maintenance_requests. Safe for environments where this already ran.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('maintenance_categories')) {
            Schema::create('maintenance_categories', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('status')->default('active');
                $table->timestamps();
            });
        }

        if (
            Schema::hasTable('maintenance_requests')
            && ! Schema::hasColumn('maintenance_requests', 'maintenance_category_id')
        ) {
            Schema::table('maintenance_requests', function (Blueprint $table) {
                $table->foreignId('maintenance_category_id')
                    ->nullable()
                    ->after('title')
                    ->constrained('maintenance_categories')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (
            Schema::hasTable('maintenance_requests')
            && Schema::hasColumn('maintenance_requests', 'maintenance_category_id')
        ) {
            Schema::table('maintenance_requests', function (Blueprint $table) {
                $table->dropConstrainedForeignId('maintenance_category_id');
            });
        }

        Schema::dropIfExists('maintenance_categories');
    }
};
