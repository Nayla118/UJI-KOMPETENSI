<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromoRedemption extends Model
{
    use HasFactory;

    public const STATUS_APPLIED = 'applied';

    public const STATUS_RELEASED = 'released';

    public const STATUSES = [
        self::STATUS_APPLIED,
        self::STATUS_RELEASED,
    ];

    protected $fillable = [
        'promo_code_id',
        'user_id',
        'booking_id',
        'discount_amount',
        'status',
    ];

    protected $casts = [
        'discount_amount' => 'decimal:2',
    ];

    public function promoCode(): BelongsTo
    {
        return $this->belongsTo(PromoCode::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function isApplied(): bool
    {
        return $this->status === self::STATUS_APPLIED;
    }
}
