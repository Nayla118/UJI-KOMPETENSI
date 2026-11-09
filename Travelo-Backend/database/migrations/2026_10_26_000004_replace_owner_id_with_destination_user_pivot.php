<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Replace the direct owner_id column (one owner per destination) with a
     * many-to-many destination_user pivot table so one owner can manage many
     * destinations and one destination can have multiple owner accounts.
     * Also adds is_active to users for account activation/deactivation.
     */
    public function up(): void
    {
        // Remove the direct owner_id column from destinations.
        Schema::table('destinations', function (Blueprint $table) {
            $table->dropIndex(['owner_id']);
            $table->dropForeign(['owner_id']);
            $table->dropColumn('owner_id');
        });

        // Pivot table: many owners per destination, many destinations per owner.
        Schema::create('destination_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('destination_id')->constrained('destinations')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'destination_id']);
        });

        // Account activation flag.
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('role');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['is_active']);
            $table->dropColumn('is_active');
        });

        Schema::dropIfExists('destination_user');

        Schema::table('destinations', function (Blueprint $table) {
            $table->foreignId('owner_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->after('category');
            $table->index(['owner_id']);
        });
    }
};
