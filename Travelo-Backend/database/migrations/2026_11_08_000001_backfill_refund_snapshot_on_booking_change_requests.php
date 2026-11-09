<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Guarantee the refund snapshot columns exist and backfill refunds that were
 * marked `refunded` before the snapshot was introduced.
 *
 * The columns were already added by 2026_10_26_000002, so the schema part is
 * defensive only (`Schema::hasColumn`). The important part is the data
 * migration: legacy refunded rows have a NULL `refund_amount`, which would make
 * the reports show a zero refund. For those rows the amount is copied from the
 * booking total and the percentage is assumed to be 100%, matching the old
 * "full refund" behaviour. No row is ever deleted or overwritten when a
 * snapshot already exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('booking_change_requests', 'refund_percentage')) {
            Schema::table('booking_change_requests', function (Blueprint $table) {
                $table->integer('refund_percentage')->nullable()->default(null)->after('refund_status');
            });
        }

        if (! Schema::hasColumn('booking_change_requests', 'refund_amount')) {
            Schema::table('booking_change_requests', function (Blueprint $table) {
                $table->decimal('refund_amount', 12, 2)->nullable()->default(null)->after('refund_percentage');
            });
        }

        DB::table('booking_change_requests')
            ->where('refund_status', 'refunded')
            ->whereNull('refund_amount')
            ->orderBy('id')
            ->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    $total = DB::table('bookings')
                        ->where('id', $row->booking_id)
                        ->value('total_price');

                    if ($total === null) {
                        continue;
                    }

                    DB::table('booking_change_requests')
                        ->where('id', $row->id)
                        ->update([
                            'refund_amount' => $total,
                            'refund_percentage' => DB::raw('COALESCE(refund_percentage, 100)'),
                        ]);
                }
            });
    }

    /**
     * Data backfill is intentionally not reversible: the previous NULL values
     * cannot be distinguished from values entered later.
     */
    public function down(): void
    {
        //
    }
};
