<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PromoCode extends Model
{
    use HasFactory, SoftDeletes;

    public const TYPE_PERCENT = 'percent';

    public const TYPE_FIXED = 'fixed';

    public const TYPES = [
        self::TYPE_PERCENT,
        self::TYPE_FIXED,
    ];

    protected $fillable = [
        'code',
        'description',
        'discount_type',
        'discount_value',
        'max_discount',
        'min_transaction',
        'usage_limit',
        'per_user_limit',
        'used_count',
        'starts_at',
        'expires_at',
        'is_active',
        'tour_package_id',
        'destination_id',
    ];

    protected $casts = [
        'discount_value' => 'decimal:2',
        'max_discount' => 'decimal:2',
        'min_transaction' => 'decimal:2',
        'usage_limit' => 'integer',
        'per_user_limit' => 'integer',
        'used_count' => 'integer',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    /**
     * Codes are always stored uppercase so lookups are case-insensitive
     * without relying on a database collation.
     */
    public function setCodeAttribute(mixed $value): void
    {
        $this->attributes['code'] = strtoupper(trim((string) $value));
    }

    public static function normalizeCode(?string $code): string
    {
        return strtoupper(trim((string) $code));
    }

    public function isPercent(): bool
    {
        return $this->discount_type === self::TYPE_PERCENT;
    }

    public function tourPackage(): BelongsTo
    {
        return $this->belongsTo(TourPackage::class);
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(Destination::class);
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(PromoRedemption::class);
    }

    /**
     * Redemptions that still consume quota. Released rows are kept for audit
     * but do not count against usage or per-user limits.
     */
    public function activeRedemptions(): HasMany
    {
        return $this->redemptions()->where('status', PromoRedemption::STATUS_APPLIED);
    }

    public function scopeUsable(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function getRemainingUsesAttribute(): ?int
    {
        if ($this->usage_limit === null) {
            return null;
        }

        return max(0, (int) $this->usage_limit - (int) $this->used_count);
    }

    public function getDiscountTypeLabelAttribute(): string
    {
        return $this->isPercent() ? 'Percent' : 'Fixed';
    }
}
