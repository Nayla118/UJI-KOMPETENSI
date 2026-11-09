<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Booking extends Model
{
    use HasFactory;

    /**
     * Payment state and ticket usage are separate dimensions:
     * a paid booking starts as an unused ticket, and only a verified gate
     * scan may mark it used. "expired" is derived from the visit date.
     */
    public const TICKET_UNUSED = 'unused';

    public const TICKET_USED = 'used';

    public const TICKET_EXPIRED = 'expired';

    /**
     * A booking counts as successful for the "popular" sort only when the
     * payment settled and the trip itself was not cancelled.
     */
    public const SUCCESSFUL_PAYMENT_STATUS = 'paid';

    public const SUCCESSFUL_BOOKING_STATUSES = ['confirmed', 'completed'];

    protected $fillable = [
        'user_id',
        'tour_package_id',
        'booking_date',
        'people_count',
        'subtotal_amount',
        'discount_amount',
        'promo_code_id',
        'total_price',
        'payment_status',
        'booking_status',
        'payment_expires_at',
        'ticket_status',
        'ticket_used_at',
        'ticket_used_by',
        'ticket_version',
        'privacy_consent_at',
        'privacy_consent_version',
    ];

    protected $casts = [
        'subtotal_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_price' => 'decimal:2',
        'booking_date' => 'date',
        'payment_expires_at' => 'datetime',
        'ticket_used_at' => 'datetime',
        'ticket_version' => 'integer',
        'privacy_consent_at' => 'datetime',
    ];

    protected $appends = [
        'ticket_usage_status',
        'promo_code',
        'payment_expires_in_seconds',
        'payment_expired',
    ];

    /**
     * Bookings that count towards popularity. Pending payments and cancelled
     * trips never inflate the ranking.
     */
    public function scopeSuccessful(Builder $query): Builder
    {
        return $query
            ->where('payment_status', self::SUCCESSFUL_PAYMENT_STATUS)
            ->whereIn('booking_status', self::SUCCESSFUL_BOOKING_STATUSES);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tourPackage(): BelongsTo
    {
        return $this->belongsTo(TourPackage::class, 'tour_package_id');
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    /**
     * The promo code used for this booking, if any. The redemption row keeps
     * the discount snapshot even after the promo is edited or deleted.
     */
    public function promo(): BelongsTo
    {
        return $this->belongsTo(PromoCode::class, 'promo_code_id');
    }

    public function promoRedemption(): HasOne
    {
        return $this->hasOne(PromoRedemption::class);
    }

    public function visitors(): HasMany
    {
        // Booker first, then remaining travellers in creation order.
        return $this->hasMany(BookingVisitor::class)
            ->orderByDesc('is_booker')
            ->orderBy('id');
    }

    /**
     * A booking can have at most one review. The database unique index on
     * reviews.booking_id is the final guard against concurrent submissions.
     */
    public function review(): HasOne
    {
        return $this->hasOne(Review::class);
    }

    /**
     * Cancellation requests, newest first.
     */
    public function changeRequests(): HasMany
    {
        return $this->hasMany(BookingChangeRequest::class)->latest('id');
    }

    /**
     * The request whose status is shown on the traveller's booking card.
     */
    public function latestChangeRequest(): HasOne
    {
        return $this->hasOne(BookingChangeRequest::class)->latestOfMany();
    }

    /**
     * Usage status shown to travellers and gate staff. Stored as used or
     * unused; expiry is computed so it can never go stale.
     */
    public function getTicketUsageStatusAttribute(): string
    {
        if (($this->attributes['ticket_status'] ?? self::TICKET_UNUSED) === self::TICKET_USED) {
            return self::TICKET_USED;
        }

        $visitDate = $this->booking_date?->toDateString();
        $today = now()->timezone((string) config('travelo.ticket.timezone', config('app.timezone')))->toDateString();

        if ($visitDate !== null && $visitDate < $today) {
            return self::TICKET_EXPIRED;
        }

        return self::TICKET_UNUSED;
    }

    /**
     * Plain code for API payloads. Kept as an accessor so every booking
     * response exposes the applied promo without leaking the relation shape.
     */
    public function getPromoCodeAttribute(): ?string
    {
        // Eloquent treats `getPromoCodeAttribute` as an accessor for the
        // camelCase relation name too, so resolve the `promo` relation
        // explicitly instead of using property access (which would recurse).
        $relation = $this->relationLoaded('promo')
            ? $this->getRelation('promo')
            : $this->getRelationValue('promo');

        return $relation?->code;
    }

    /**
     * True while the traveller still has time to complete the payment.
     * A paid booking never counts as awaiting payment, even if a stale
     * deadline is in the past.
     */
    public function isAwaitingPayment(): bool
    {
        return $this->booking_status === 'pending'
            && $this->payment_status === 'pending';
    }

    /**
     * The payment window is absolute: after it passes the seats are released,
     * regardless of whether the scheduler has already run. Paid bookings are
     * never considered expired.
     */
    public function paymentWindowExpired(): bool
    {
        if ($this->payment_status === 'paid' || $this->payment_expires_at === null) {
            return false;
        }

        return $this->payment_expires_at->getTimestamp() <= now()->getTimestamp();
    }

    /**
     * Whole seconds left in the payment window. The server computes this with
     * its own clock so clients can render a countdown without trusting the
     * device clock. Returns 0 once the window is over.
     */
    public function getPaymentExpiresInSecondsAttribute(): ?int
    {
        if ($this->payment_expires_at === null) {
            return null;
        }

        if ($this->payment_status === 'paid') {
            return 0;
        }

        return max(0, $this->payment_expires_at->getTimestamp() - now()->getTimestamp());
    }

    public function getPaymentExpiredAttribute(): bool
    {
        return $this->paymentWindowExpired();
    }

    /**
     * Legacy bookings never stored a subtotal. Deriving it from the final
     * total plus the discount keeps old invoices readable.
     */
    public function effectiveSubtotal(): float
    {
        if ($this->subtotal_amount !== null) {
            return (float) $this->subtotal_amount;
        }

        return (float) $this->total_price + (float) $this->discount_amount;
    }

    public function isPaidAndConfirmed(): bool
    {
        return $this->payment_status === 'paid'
            && $this->booking_status === 'confirmed'
            && $this->payment?->paid_at !== null;
    }

    /**
     * Legacy bookings created before visitor identities existed have no
     * visitors: they stay eligible for an e-ticket. Once a booking has
     * visitors, all of them (and the count) must be complete.
     */
    public function hasCompleteVisitorIdentity(): bool
    {
        $visitors = $this->relationLoaded('visitors')
            ? $this->visitors
            : $this->visitors()->get();

        if ($visitors->isEmpty()) {
            return true;
        }

        if ($visitors->count() !== (int) $this->people_count) {
            return false;
        }

        return $visitors->every(fn (BookingVisitor $visitor) => $visitor->is_identity_complete);
    }
}
