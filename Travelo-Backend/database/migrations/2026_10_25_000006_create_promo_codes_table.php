<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promo_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('description')->nullable();
            $table->enum('discount_type', ['percent', 'fixed']);
            $table->decimal('discount_value', 12, 2);
            $table->decimal('max_discount', 12, 2)->nullable();
            $table->decimal('min_transaction', 12, 2)->default(0);
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('per_user_limit')->default(1);
            $table->unsignedInteger('used_count')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);

            // Optional restrictions. Null means the promo is valid for every
            // package/destination.
            $table->foreignId('tour_package_id')->nullable()
                ->constrained('tour_packages')->nullOnDelete();
            $table->foreignId('destination_id')->nullable()
                ->constrained('destinations')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'starts_at', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promo_codes');
    }
};
