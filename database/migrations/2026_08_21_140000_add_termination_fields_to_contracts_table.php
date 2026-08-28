<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table): void {
            if (! Schema::hasColumn('contracts', 'termination_date')) {
                $table->date('termination_date')->nullable()->after('status');
            }

            if (! Schema::hasColumn('contracts', 'termination_reason')) {
                $table->text('termination_reason')->nullable()->after('termination_date');
            }
        });
    }

    public function down(): void
    {
        // Compatibility migration: keep termination fields because older
        // lifecycle migrations and current code may also depend on them.
    }
};
