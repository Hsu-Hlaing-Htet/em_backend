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
        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->string('property_code')->unique();
            $table->string('property_name');
            $table->string('property_type'); // apartment, condo, house
            $table->string('purpose'); // sale, rent
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('building')->nullable();
            $table->string('floor')->nullable();
            $table->string('unit_number')->nullable();
            $table->string('township');
            $table->string('address');
            $table->unsignedTinyInteger('bedrooms')->nullable();
            $table->unsignedTinyInteger('bathrooms')->nullable();
            $table->unsignedInteger('area_sqft')->nullable();
            $table->string('status')->default('available');
            $table->decimal('sale_price', 14, 2)->nullable();
            $table->decimal('monthly_rent', 14, 2)->nullable();
            $table->decimal('maintenance_fee', 14, 2)->default(0);
            $table->text('description')->nullable();
            $table->string('featured_image')->nullable();
            $table->json('gallery_images')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->date('listed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['property_type', 'purpose']);
            $table->index(['status', 'township']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};
