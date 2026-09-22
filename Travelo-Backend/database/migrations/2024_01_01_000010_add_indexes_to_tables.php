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
        // Add indexes to tour_packages table
        Schema::table('tour_packages', function (Blueprint $table) {
            $table->index('destination_id'); // Foreign key index
            $table->index('created_at'); // For ordering
            $table->index('price'); // For price filtering
            $table->index('duration_days'); // For duration filtering
            $table->index('rating'); // For rating sorting
        });

        // Add indexes to destinations table
        Schema::table('destinations', function (Blueprint $table) {
            $table->index('is_popular'); // For popular destinations query
            $table->index('created_at'); // For ordering
            $table->index('country'); // For country filtering
            $table->index('rating'); // For rating sorting
        });

        // Add indexes to bookings table
        Schema::table('bookings', function (Blueprint $table) {
            $table->index('user_id'); // For user bookings query
            $table->index('tour_package_id'); // For tour package bookings
            $table->index('payment_status'); // For status filtering
            $table->index('booking_status'); // For status filtering
            $table->index('created_at'); // For ordering
        });

        // Add indexes to payments table
        Schema::table('payments', function (Blueprint $table) {
            $table->index('booking_id'); // For booking payment query
            $table->index('payment_status'); // For status filtering
            $table->index('created_at'); // For ordering
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tour_packages', function (Blueprint $table) {
            $table->dropIndex(['destination_id']);
            $table->dropIndex(['created_at']);
            $table->dropIndex(['price']);
            $table->dropIndex(['duration_days']);
            $table->dropIndex(['rating']);
        });

        Schema::table('destinations', function (Blueprint $table) {
            $table->dropIndex(['is_popular']);
            $table->dropIndex(['created_at']);
            $table->dropIndex(['country']);
            $table->dropIndex(['rating']);
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->dropIndex(['tour_package_id']);
            $table->dropIndex(['status']);
            $table->dropIndex(['created_at']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['booking_id']);
            $table->dropIndex(['status']);
            $table->dropIndex(['created_at']);
        });
    }
};
