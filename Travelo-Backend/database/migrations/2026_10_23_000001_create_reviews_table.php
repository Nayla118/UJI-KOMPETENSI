<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Verified-visit destination reviews.
 *
 * A review belongs to exactly one booking (unique booking_id), which is what
 * makes "one review per visit" safe even under concurrent submissions. The
 * destination and user ids are stored for fast public aggregates and owner
 * lookups, but both are always derived again from the booking server-side.
 *
 * status is "published" or "hidden". Only published reviews feed public
 * averages, counts and distribution. Hidden reviews stay available to their
 * owner and to admins for moderation/audit.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('reviews')) {
            return;
        }

        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('destination_id')->constrained()->cascadeOnDelete();

            // Integer 1..5, additionally validated by the API and admin code.
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();

            // published | hidden
            $table->string('status', 20)->default('published');
            $table->string('moderation_reason', 500)->nullable();
            $table->foreignId('moderated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('moderated_at')->nullable();

            $table->timestamps();

            $table->index(['destination_id', 'status', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
