<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Travel-agent detail content: what is included/excluded, where travellers
 * meet, and the terms & conditions. Every column is nullable so existing
 * packages keep working unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tour_packages', function (Blueprint $table) {
            if (! Schema::hasColumn('tour_packages', 'inclusions')) {
                $table->json('inclusions')->nullable()->after('image');
            }
            if (! Schema::hasColumn('tour_packages', 'exclusions')) {
                $table->json('exclusions')->nullable()->after('inclusions');
            }
            if (! Schema::hasColumn('tour_packages', 'meeting_point')) {
                $table->string('meeting_point')->nullable()->after('exclusions');
            }
            if (! Schema::hasColumn('tour_packages', 'meeting_latitude')) {
                $table->decimal('meeting_latitude', 10, 7)->nullable()->after('meeting_point');
            }
            if (! Schema::hasColumn('tour_packages', 'meeting_longitude')) {
                $table->decimal('meeting_longitude', 10, 7)->nullable()->after('meeting_latitude');
            }
            if (! Schema::hasColumn('tour_packages', 'terms')) {
                $table->text('terms')->nullable()->after('meeting_longitude');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tour_packages', function (Blueprint $table) {
            foreach (['terms', 'meeting_longitude', 'meeting_latitude', 'meeting_point', 'exclusions', 'inclusions'] as $column) {
                if (Schema::hasColumn('tour_packages', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
