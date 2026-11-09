<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Raw Midtrans `payment_type` (gopay, qris, bank_transfer, ...). The
 * normalized label stays in `payment_method`; keeping the raw value makes the
 * admin payment detail and invoices explicit about what the gateway reported.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (! Schema::hasColumn('payments', 'payment_type')) {
                $table->string('payment_type', 50)->nullable()->after('payment_method');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (Schema::hasColumn('payments', 'payment_type')) {
                $table->dropColumn('payment_type');
            }
        });
    }
};
