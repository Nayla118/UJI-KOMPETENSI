<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Visitor identity documents + ticket usage lifecycle.
 *
 * Everything here is additive and nullable-safe so existing bookings, old
 * e-ticket tokens and existing admin accounts keep working:
 *
 * - bookings get a separate ticket usage dimension (unused/used). "expired"
 *   is derived from the visit date instead of being stored, so it can never
 *   become stale.
 * - booking_visitors stores one row per traveller (the booker is one of them)
 *   plus a private identity document reference. Upload sets document_status
 *   to "pending"; only an explicit staff review sets "verified".
 * - users get a role + optional destination scope for gate staff. Existing
 *   users with a password (the admin account) are promoted to admin so
 *   admin-panel login is unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('booking_visitors')) {
            Schema::create('booking_visitors', function (Blueprint $table) {
                $table->id();
                $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
                $table->boolean('is_booker')->default(false);
                $table->string('name', 100);

                // ktp | kia | student_card | other
                $table->string('identity_type', 20);
                $table->string('identity_type_other', 60)->nullable();
                $table->string('identity_number', 64)->nullable();
                $table->date('date_of_birth')->nullable();
                $table->string('phone', 30)->nullable();

                // Private disk reference only. Never the original file name.
                $table->string('document_path')->nullable();
                $table->string('document_mime', 100)->nullable();
                $table->unsignedInteger('document_size')->nullable();
                // missing | pending | verified | rejected | purged
                $table->string('document_status', 20)->default('missing');
                $table->timestamp('document_uploaded_at')->nullable();
                $table->timestamp('document_reviewed_at')->nullable();
                $table->unsignedBigInteger('document_reviewed_by')->nullable();
                $table->string('document_rejection_reason', 255)->nullable();
                $table->timestamp('document_purged_at')->nullable();

                $table->timestamps();

                $table->index(['booking_id', 'is_booker']);
                $table->index('document_status');
            });
        }

        Schema::table('bookings', function (Blueprint $table) {
            if (!Schema::hasColumn('bookings', 'ticket_status')) {
                $table->string('ticket_status', 20)->default('unused');
            }
            if (!Schema::hasColumn('bookings', 'ticket_used_at')) {
                $table->timestamp('ticket_used_at')->nullable();
            }
            if (!Schema::hasColumn('bookings', 'ticket_used_by')) {
                $table->unsignedBigInteger('ticket_used_by')->nullable();
            }
            if (!Schema::hasColumn('bookings', 'ticket_version')) {
                $table->unsignedInteger('ticket_version')->default(1);
            }
            if (!Schema::hasColumn('bookings', 'privacy_consent_at')) {
                $table->timestamp('privacy_consent_at')->nullable();
            }
            if (!Schema::hasColumn('bookings', 'privacy_consent_version')) {
                $table->string('privacy_consent_version', 20)->nullable();
            }
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->index('ticket_status');
        });

        if (!Schema::hasColumn('users', 'role')) {
            Schema::table('users', function (Blueprint $table) {
                // traveller | admin | staff
                $table->string('role', 20)->default('traveller');
            });
        }

        // The admin panel authenticates against users that have a local
        // password; Firebase travellers never do. Preserve that access.
        DB::table('users')
            ->whereNotNull('password')
            ->where('password', '<>', '')
            ->where('role', 'traveller')
            ->update(['role' => 'admin']);

        if (!Schema::hasTable('staff_destinations')) {
            Schema::create('staff_destinations', function (Blueprint $table) {
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('destination_id')->constrained()->cascadeOnDelete();
                $table->primary(['user_id', 'destination_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_destinations');

        if (Schema::hasColumn('users', 'role')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('role');
            });
        }

        Schema::table('bookings', function (Blueprint $table) {
            foreach ([
                'privacy_consent_version',
                'privacy_consent_at',
                'ticket_version',
                'ticket_used_by',
                'ticket_used_at',
                'ticket_status',
            ] as $column) {
                if (Schema::hasColumn('bookings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::dropIfExists('booking_visitors');
    }
};
