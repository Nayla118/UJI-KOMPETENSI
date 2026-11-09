<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_change_requests', function (Blueprint $table) {
            // Snapshot of the refund policy calculation at approval time.
            // Money uses the same decimal precision as bookings.total_price.
            $table->integer('refund_percentage')->nullable()->default(null)->after('refund_status');
            $table->decimal('refund_amount', 12, 2)->nullable()->default(null)->after('refund_percentage');

            // Extend refund_status to cover the auto-refund lifecycle. Old
            // records keep 'not_applicable' / 'pending' / 'refunded'; new
            // values are 'processing' and 'failed'.
            $table->string('refund_status', 20)
                ->default('not_applicable')
                ->change();

            // Midtrans refund reference (for auto-refund) or manual reference.
            $table->string('refund_reference', 255)->nullable()->after('refund_amount');
            $table->text('refund_failure_reason')->nullable()->after('refund_reference');

            $table->index(['refund_status']);
        });
    }

    public function down(): void
    {
        Schema::table('booking_change_requests', function (Blueprint $table) {
            $table->dropIndex(['refund_status']);
            $table->dropColumn([
                'refund_percentage',
                'refund_amount',
                'refund_reference',
                'refund_failure_reason',
            ]);
            $table->string('refund_status', 20)->default('not_applicable')->change();
        });
    }
};
