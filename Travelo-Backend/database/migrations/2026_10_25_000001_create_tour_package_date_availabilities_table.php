<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-date capacity overrides. When a date has no row, the tour package's
     * `max_people` is used as that day's capacity.
     */
    public function up(): void
    {
        Schema::create('tour_package_date_availabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tour_package_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('capacity')->nullable();
            $table->boolean('is_closed')->default(false);
            $table->string('note', 255)->nullable();
            $table->timestamps();

            $table->unique(['tour_package_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tour_package_date_availabilities');
    }
};
