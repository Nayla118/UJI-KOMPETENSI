<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Coordinates let the app open a destination on a map.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('destinations', function (Blueprint $table) {
            if (! Schema::hasColumn('destinations', 'latitude')) {
                $table->decimal('latitude', 10, 7)->nullable()->after('is_popular');
            }
            if (! Schema::hasColumn('destinations', 'longitude')) {
                $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            }
        });
    }

    public function down(): void
    {
        Schema::table('destinations', function (Blueprint $table) {
            foreach (['longitude', 'latitude'] as $column) {
                if (Schema::hasColumn('destinations', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
