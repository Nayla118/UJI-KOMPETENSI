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
        Schema::table('payments', function (Blueprint $table) {
            // Add order ID column if it doesn't exist
            if (!Schema::hasColumn('payments', 'midtrans_order_id')) {
                $table->string('midtrans_order_id')->nullable()->after('midtrans_transaction_id')->index();
            }
            
            // Add paid_at timestamp to track when payment was completed
            if (!Schema::hasColumn('payments', 'paid_at')) {
                $table->timestamp('paid_at')->nullable()->after('updated_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (Schema::hasColumn('payments', 'midtrans_order_id')) {
                $table->dropColumn('midtrans_order_id');
            }
            if (Schema::hasColumn('payments', 'paid_at')) {
                $table->dropColumn('paid_at');
            }
        });
    }
};
