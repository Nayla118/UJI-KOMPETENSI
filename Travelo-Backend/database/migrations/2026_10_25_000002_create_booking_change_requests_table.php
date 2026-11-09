<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_change_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('status', 20)->default('pending');
            $table->date('original_booking_date');
            $table->date('requested_date')->nullable();
            $table->text('reason')->nullable();
            $table->text('admin_note')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('refund_status', 20)->default('not_applicable');
            $table->timestamp('refunded_at')->nullable();
            $table->text('refund_note')->nullable();
            $table->timestamps();

            $table->index(['booking_id', 'status']);
            $table->index(['status', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_change_requests');
    }
};
