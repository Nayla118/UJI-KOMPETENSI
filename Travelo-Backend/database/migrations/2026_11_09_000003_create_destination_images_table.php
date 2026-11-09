<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gallery photos for a destination. The original schema only had a single
 * `image` column, so this adds the missing multi-image gallery additively.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('destination_images')) {
            return;
        }

        Schema::create('destination_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('destination_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('caption')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['destination_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('destination_images');
    }
};
