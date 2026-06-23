<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('utility_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('utility_type_id')->constrained('utility_types')->cascadeOnDelete();
            $table->decimal('unit_price', 10, 2);
            $table->date('effective_date');
            $table->string('status');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('utility_rates');
    }
};
