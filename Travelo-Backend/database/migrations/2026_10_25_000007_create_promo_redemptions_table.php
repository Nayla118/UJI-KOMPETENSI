<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promo_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promo_code_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // One booking can consume at most one promo code.
            $table->foreignId('booking_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->enum('status', ['applied', 'released'])->default('applied');
            $table->timestamps();

            $table->index(['promo_code_id', 'user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promo_redemptions');
    }
};
