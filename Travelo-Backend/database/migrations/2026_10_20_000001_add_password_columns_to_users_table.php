<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Session-based admin login needs a local password hash and remember token.
 * Firebase-authenticated app users do not, so both columns stay nullable.
 *
 * The check keeps this migration safe on databases that already have the
 * columns (the development database does).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'password')) {
                $table->string('password')->nullable()->after('email');
            }

            if (!Schema::hasColumn('users', 'remember_token')) {
                $table->rememberToken();
            }
        });
    }

    public function down(): void
    {
        // Intentionally non-destructive: dropping password/remember_token
        // would break admin sign-in on existing installations.
    }
};
