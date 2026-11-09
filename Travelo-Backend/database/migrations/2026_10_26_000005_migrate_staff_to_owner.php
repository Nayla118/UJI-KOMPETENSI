<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Data migration: convert legacy staff accounts to owner, and move
     * staff_destinations pivot assignments to destination_user.
     *
     * This is additive and non-destructive: the staff_destinations table is
     * kept (not dropped) so old references remain intact, but no new writes
     * go to it.
     */
    public function up(): void
    {
        if (! Schema::hasTable('staff_destinations')) {
            return;
        }

        // Migrate staff_destinations pivot rows to destination_user,
        // skipping any that already exist.
        DB::table('destination_user')->insertUsing(
            ['user_id', 'destination_id', 'created_at', 'updated_at'],
            DB::table('staff_destinations')
                ->select('user_id', 'destination_id', 'created_at', 'updated_at')
                ->whereNotExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('destination_user')
                        ->whereColumn('destination_user.user_id', 'staff_destinations.user_id')
                        ->whereColumn('destination_user.destination_id', 'staff_destinations.destination_id');
                })
        );

        // Convert legacy staff role to owner.
        DB::table('users')
            ->where('role', 'staff')
            ->update(['role' => 'owner']);
    }

    public function down(): void
    {
        // No reverse data migration: keeping the data as owner is safe.
    }
};
