<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->string('contract_code')->unique();
            $table->string('contract_type'); // sale, lease
            $table->foreignId('related_property_id')->constrained('properties')->cascadeOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('payment_plan')->nullable(); // full, installment
            $table->unsignedInteger('number_of_months')->nullable();
            $table->unsignedTinyInteger('monthly_due_day')->nullable();
            $table->string('status')->default('draft');
            $table->text('terms')->nullable();
            $table->timestamps();

            $table->index(['contract_type', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
