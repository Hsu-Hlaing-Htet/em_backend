<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('utility_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('utility_id')->constrained()->cascadeOnDelete();
            $table->foreignId('utility_type_id')->constrained()->cascadeOnDelete();
            $table->decimal('previous_reading', 14, 2)->default(0);
            $table->decimal('current_reading', 14, 2)->default(0);
            $table->decimal('usage', 14, 2)->default(0);
            $table->decimal('unit_price', 14, 4)->default(0);
            $table->decimal('amount', 14, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('utility_items');
    }
};
