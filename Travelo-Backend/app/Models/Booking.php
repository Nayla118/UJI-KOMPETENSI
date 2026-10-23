<?php

namespace App\Models;

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

    protected $fillable = [
        'user_id',
        'tour_package_id',
        'booking_date',
        'people_count',
        'total_price',
        'payment_status',
        'booking_status',
        'ticket_status',
        'ticket_used_at',
        'ticket_used_by',
        'ticket_version',
        'privacy_consent_at',
        'privacy_consent_version',
    ];

    protected $casts = [
        'total_price' => 'decimal:2',
        'booking_date' => 'date',
        'ticket_used_at' => 'datetime',
        'ticket_version' => 'integer',
        'privacy_consent_at' => 'datetime',
    ];

    protected $appends = [
        'ticket_usage_status',
    ];

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
