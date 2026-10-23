<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Account-level identity data for the booker.
 *
 * A traveller only has to enter their own identity once. The first booking
 * stores a copy here; later bookings can prefill the booker row and reuse the
 * private document copy without uploading it again.
 *
 * This is separate from booking_visitors because visitor documents follow the
 * booking retention rule (90 days after the visit). The saved copy belongs to
 * the account and lives until the traveller replaces or deletes it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('saved_identities')) {
            Schema::create('saved_identities', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

                $table->string('name', 100);
                // ktp | kia | student_card | other
                $table->string('identity_type', 20);
                $table->string('identity_type_other', 60)->nullable();
                $table->string('identity_number', 64);
                $table->date('date_of_birth')->nullable();
                $table->string('phone', 30)->nullable();

                // Private disk reference only. Never the original file name.
                $table->string('document_path')->nullable();
                $table->string('document_mime', 100)->nullable();
                $table->unsignedInteger('document_size')->nullable();
                // missing | pending | verified | rejected
                $table->string('document_status', 20)->default('missing');
                $table->timestamp('document_uploaded_at')->nullable();
                $table->timestamp('document_reviewed_at')->nullable();
                $table->unsignedBigInteger('document_reviewed_by')->nullable();
                $table->string('document_rejection_reason', 255)->nullable();

                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_identities');
    }
};
