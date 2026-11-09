<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive catalog filtering support.
 *
 * `destinations.category` is the only column the filter/sort feature needs
 * that did not exist yet. `destinations.city`, `destinations.country` and
 * `tour_packages.duration_days` are already part of the original schema, so
 * they are intentionally not re-created here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('destinations', function (Blueprint $table) {
            if (! Schema::hasColumn('destinations', 'category')) {
                $table->string('category', 30)->nullable()->after('country');
                $table->index('category');
            }
        });
    }

    public function down(): void
    {
        Schema::table('destinations', function (Blueprint $table) {
            if (Schema::hasColumn('destinations', 'category')) {
                $table->dropIndex(['category']);
                $table->dropColumn('category');
            }
        });
    }
};
