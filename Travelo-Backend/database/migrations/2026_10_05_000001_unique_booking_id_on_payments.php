<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One payment row per booking: dedupe legacy duplicates, then enforce
     * uniqueness so concurrent duplicate webhooks can never create a
     * second Payment for the same booking (idempotency at DB level).
     */
    public function up(): void
    {
        // Remove accidental duplicates, keeping the oldest row per booking.
        $duplicates = DB::table('payments')
            ->select('booking_id', DB::raw('MIN(id) as keep_id'), DB::raw('COUNT(*) as c'))
            ->groupBy('booking_id')
            ->having('c', '>', 1)
            ->get();

        foreach ($duplicates as $dup) {
            DB::table('payments')
                ->where('booking_id', $dup->booking_id)
                ->where('id', '!=', $dup->keep_id)
                ->delete();
        }

        Schema::table('payments', function (Blueprint $table) {
            $table->unique('booking_id', 'payments_booking_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique('payments_booking_id_unique');
        });
    }
};
