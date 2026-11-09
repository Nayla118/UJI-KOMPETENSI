<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Supporting indexes for the admin analytics queries.
 *
 * Additive only: no column is changed or dropped. Every report filter starts
 * from a date range plus a status, so these composite indexes keep the
 * GROUP BY queries cheap on both SQLite (development) and MySQL (production).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->index(['payment_status', 'created_at'], 'bookings_payment_created_idx');
            $table->index(['booking_status', 'created_at'], 'bookings_status_created_idx');
            $table->index(['tour_package_id', 'booking_date'], 'bookings_package_date_idx');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->index('paid_at', 'payments_paid_at_idx');
        });

        Schema::table('booking_change_requests', function (Blueprint $table) {
            $table->index(['refund_status', 'refunded_at'], 'change_requests_refund_idx');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index(['role', 'created_at'], 'users_role_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex('bookings_payment_created_idx');
            $table->dropIndex('bookings_status_created_idx');
            $table->dropIndex('bookings_package_date_idx');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('payments_paid_at_idx');
        });

        Schema::table('booking_change_requests', function (Blueprint $table) {
            $table->dropIndex('change_requests_refund_idx');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_role_created_idx');
        });
    }
};
