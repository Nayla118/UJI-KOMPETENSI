<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tambah kolom pelengkap Midtrans TANPA mengubah kolom lama.
     * Semua nullable agar data lama tetap valid (backward-compatible).
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (!Schema::hasColumn('payments', 'snap_token')) {
                $table->string('snap_token', 100)->nullable()->after('midtrans_order_id');
            }
            if (!Schema::hasColumn('payments', 'payment_channel')) {
                // Contoh: gopay, qris, bca, bri, visa, indomaret, alfamart
                $table->string('payment_channel', 50)->nullable()->after('payment_method');
            }
            if (!Schema::hasColumn('payments', 'transaction_status')) {
                // Status mentah Midtrans: settlement, capture, pending, deny, expire, cancel, etc.
                $table->string('transaction_status', 50)->nullable()->after('payment_channel');
            }
            if (!Schema::hasColumn('payments', 'fraud_status')) {
                $table->string('fraud_status', 20)->nullable()->after('transaction_status');
            }
            if (!Schema::hasColumn('payments', 'expires_at')) {
                $table->timestamp('expires_at')->nullable()->after('paid_at');
            }
            if (!Schema::hasColumn('payments', 'metadata')) {
                // va_numbers, actions (QR/deeplink), bill_key, store, dsb (JSON).
                $table->json('metadata')->nullable()->after('expires_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            foreach (['metadata', 'expires_at', 'fraud_status', 'transaction_status', 'payment_channel', 'snap_token'] as $col) {
                if (Schema::hasColumn('payments', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
