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
        Schema::create('viewing_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            $table->string('requester_name');
            $table->string('email');
            $table->string('phone');
            $table->text('message')->nullable();
            $table->date('preferred_date')->nullable();
            $table->string('request_type')->default('viewing'); // viewing, booking
            $table->string('status')->default('pending'); // pending, approved, rejected, reserved
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reservation_expires_at')->nullable();
            $table->timestamps();

            $table->index(['request_type', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('viewing_requests');
    }
};
