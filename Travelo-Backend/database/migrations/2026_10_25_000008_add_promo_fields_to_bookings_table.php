<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Legacy bookings keep NULL here; their subtotal is derived from
            // total_price + discount_amount (discount is 0 for them).
            $table->decimal('subtotal_amount', 12, 2)->nullable()->after('people_count');
            $table->decimal('discount_amount', 12, 2)->default(0)->after('subtotal_amount');
            // Plain index instead of a foreign key keeps this migration
            // additive on every driver (SQLite cannot add FK constraints to
            // an existing table without a full rebuild).
            $table->unsignedBigInteger('promo_code_id')->nullable()->after('discount_amount');
            $table->index('promo_code_id');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(['promo_code_id']);
            $table->dropColumn(['subtotal_amount', 'discount_amount', 'promo_code_id']);
        });
    }
};
