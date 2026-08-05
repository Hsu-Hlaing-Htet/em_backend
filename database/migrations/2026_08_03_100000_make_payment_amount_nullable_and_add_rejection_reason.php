<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('amount', 14, 2)->nullable()->change();
            $table->text('rejection_reason')->nullable()->after('note');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('rejection_reason');
            $table->decimal('amount', 14, 2)->nullable(false)->change();
        });
    }
};
