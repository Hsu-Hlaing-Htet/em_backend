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
            $table->foreignId('building_id')->constrained('buildings')->cascadeOnDelete();
            $table->string('room_number');
            $table->unsignedInteger('floor_number');
            $table->decimal('area_sqft', 10, 2);
            $table->text('description')->nullable();
            $table->string('type');
            $table->string('status');
            $table->decimal('sale_price', 12, 2)->default(0);
            $table->decimal('rent_price', 12, 2)->default(0);
            $table->decimal('rent_deposit_price', 12, 2)->default(0);
            $table->decimal('booking_deposit_price', 12, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index('building_id');
            $table->index('room_number');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
