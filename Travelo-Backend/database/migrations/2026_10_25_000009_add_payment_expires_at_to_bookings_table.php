<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Legacy bookings keep NULL: they had no payment deadline. New
            // bookings always receive now() + travelo.payment.ttl_minutes.
            $table->timestamp('payment_expires_at')
                ->nullable()
                ->after('booking_status');

            $table->index('payment_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(['payment_expires_at']);
            $table->dropColumn('payment_expires_at');
        });
    }
};
