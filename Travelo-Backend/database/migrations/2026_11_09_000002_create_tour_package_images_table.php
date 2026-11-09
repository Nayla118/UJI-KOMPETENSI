<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gallery photos for a tour package, beyond the single cover `image` column.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tour_package_images')) {
            return;
        }

        Schema::create('tour_package_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tour_package_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('caption')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['tour_package_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tour_package_images');
    }
};
