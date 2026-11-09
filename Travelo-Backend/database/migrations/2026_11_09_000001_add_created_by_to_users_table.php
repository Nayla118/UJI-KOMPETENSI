<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive audit link for staff accounts created by an owner.
 *
 * Owners may create manager/gate_staff accounts for their own destinations.
 * `created_by` records which account created the staff member, so an owner
 * can never edit staff that belongs to another owner and the audit trail
 * survives account deactivation (staff are deactivated, not deleted).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'created_by')) {
            Schema::table('users', function (Blueprint $table) {
                // Plain nullable id (no FK constraint) so SQLite does not
                // rebuild the whole users table. A rebuild collides with the
                // existing `sqlite_autoindex_users_1` unique index on
                // firebase_uid. Referential integrity is enforced in the
                // application (created_by is only set from the authenticated
                // owner/admin and never exposed as user input).
                $table->unsignedBigInteger('created_by')->nullable()->after('is_active');
            });
        }

        if (! Schema::hasIndex('users', 'users_created_by_index')) {
            Schema::table('users', function (Blueprint $table) {
                $table->index('created_by');
            });
        }
    }

    public function down(): void
    {
        // Intentionally non-destructive: dropping the audit link would lose
        // ownership history for existing staff accounts.
    }
};
