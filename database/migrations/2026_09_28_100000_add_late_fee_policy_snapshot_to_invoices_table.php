<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->foreignId('late_fee_rule_id')
                ->nullable()
                ->after('late_fee')
                ->constrained('late_fees')
                ->nullOnDelete();
            $table->boolean('late_fee_waived')
                ->default(false)
                ->after('late_fee_rule_id');
            $table->string('late_fee_policy_name')
                ->nullable()
                ->after('late_fee_waived');
            $table->string('late_fee_policy_type')
                ->nullable()
                ->after('late_fee_policy_name');
            $table->decimal('late_fee_policy_value', 10, 2)
                ->nullable()
                ->after('late_fee_policy_type');
            $table->string('late_fee_policy_per')
                ->nullable()
                ->after('late_fee_policy_value');
            $table->unsignedInteger('late_fee_policy_grace_days')
                ->nullable()
                ->after('late_fee_policy_per');
            $table->boolean('late_fee_policy_locked')
                ->default(false)
                ->after('late_fee_policy_grace_days');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('late_fee_rule_id');
            $table->dropColumn([
                'late_fee_waived',
                'late_fee_policy_name',
                'late_fee_policy_type',
                'late_fee_policy_value',
                'late_fee_policy_per',
                'late_fee_policy_grace_days',
                'late_fee_policy_locked',
            ]);
        });
    }
};
