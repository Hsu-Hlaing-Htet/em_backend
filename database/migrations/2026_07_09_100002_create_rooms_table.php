<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('building_id')->constrained()->cascadeOnDelete();
            $table->string('room_number');
            $table->unsignedInteger('floor_number')->default(1);
            $table->decimal('area_sqft', 10, 2)->default(0);
            $table->text('description')->nullable();
            $table->string('type')->default('rent');
            $table->string('status')->default('available');
            $table->decimal('sale_price', 12, 2)->default(0);
            $table->decimal('rent_price', 12, 2)->default(0);
            $table->decimal('rent_deposit_price', 12, 2)->default(0);
            $table->decimal('booking_deposit_price', 12, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['building_id', 'room_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
