<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_requests', function (Blueprint $table) {
            $table->string('category')->nullable()->after('title');
            $table->string('priority')->nullable()->after('category');
            $table->text('rejection_reason')->nullable()->after('status');
            $table->text('resolution_note')->nullable()->after('rejection_reason');
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_requests', function (Blueprint $table) {
            $table->dropColumn(['category', 'priority', 'rejection_reason', 'resolution_note']);
        });
    }
};
