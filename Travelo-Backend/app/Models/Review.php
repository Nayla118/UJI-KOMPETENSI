<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Review extends Model
{
    use HasFactory;

    public const STATUS_PUBLISHED = 'published';
    public const STATUS_HIDDEN = 'hidden';

    public const STATUSES = [
        self::STATUS_PUBLISHED,
        self::STATUS_HIDDEN,
    ];

    public const MIN_RATING = 1;
    public const MAX_RATING = 5;

    protected $fillable = [
        'booking_id',
        'user_id',
        'destination_id',
        'rating',
        'comment',
        'status',
        'moderation_reason',
        'moderated_by',
        'moderated_at',
    ];

    protected $casts = [
        'rating' => 'integer',
        'moderated_at' => 'datetime',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(Destination::class);
    }

    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderated_by');
    }

    public function scopePublished($query)
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    /**
     * First name + last initial. Public review lists must never expose the
     * reviewer's full account name, email, phone or booking identity data.
     */
    public function getReviewerDisplayNameAttribute(): string
    {
        $name = trim((string) $this->user?->name);

        // A traveller could type an email address into their display name.
        // Never let that become a public reviewer name.
        $name = trim((string) preg_replace('/[^\s@]+@[^\s@]+/u', ' ', $name));

        if ($name === '') {
            return 'Travelo guest';
        }

        $parts = preg_split('/\s+/', $name) ?: [];
        $first = $parts[0] ?? 'Travelo guest';

        if (count($parts) < 2) {
            return mb_substr($first, 0, 60);
        }

        $last = $parts[count($parts) - 1];
        $initial = mb_strtoupper(mb_substr($last, 0, 1));

        return mb_substr($first, 0, 60).' '.$initial.'.';
    }

    /**
     * A published review is only shown as a verified visit when its booking
     * still carries the server-recorded gate redemption proof.
     */
    public function getIsVerifiedVisitAttribute(): bool
    {
        $booking = $this->booking;

        return $booking !== null
            && $booking->ticket_status === Booking::TICKET_USED
            && $booking->ticket_used_at !== null;
    }
}
