<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Day-by-day itinerary content for a tour package. Additive: existing packages
 * simply have zero rows and keep working.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tour_package_itinerary_days')) {
            return;
        }

        Schema::create('tour_package_itinerary_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tour_package_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('day_number');
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['tour_package_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tour_package_itinerary_days');
    }
};
