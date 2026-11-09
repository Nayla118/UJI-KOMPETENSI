<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A traveller-initiated cancellation request. Nothing changes on the booking
 * until an admin approves the request.
 *
 * The table still carries a nullable `requested_date` column from the earlier
 * reschedule design; it is no longer written or read.
 */
class BookingChangeRequest extends Model
{
    public const TYPE_CANCELLATION = 'cancellation';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    public const REFUND_NOT_APPLICABLE = 'not_applicable';

    public const REFUND_PENDING = 'pending';

    public const REFUND_PROCESSING = 'processing';

    public const REFUND_REFUNDED = 'refunded';

    public const REFUND_FAILED = 'failed';

    public const REFUND_STATUSES = [
        self::REFUND_NOT_APPLICABLE,
        self::REFUND_PENDING,
        self::REFUND_PROCESSING,
        self::REFUND_REFUNDED,
        self::REFUND_FAILED,
    ];

    public const TYPES = [
        self::TYPE_CANCELLATION,
    ];

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
        self::STATUS_CANCELLED,
    ];

    protected $fillable = [
        'booking_id',
        'user_id',
        'type',
        'status',
        'original_booking_date',
        'reason',
        'admin_note',
        'reviewed_by',
        'reviewed_at',
        'refund_status',
        'refund_percentage',
        'refund_amount',
        'refund_reference',
        'refunded_at',
        'refund_note',
        'refund_failure_reason',
    ];

    protected $casts = [
        'original_booking_date' => 'date',
        'reviewed_at' => 'datetime',
        'refunded_at' => 'datetime',
        'refund_percentage' => 'integer',
        'refund_amount' => 'decimal:2',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function refundStatusLabel(): string
    {
        return match ($this->refund_status) {
            self::REFUND_NOT_APPLICABLE => 'Not applicable',
            self::REFUND_PENDING => 'Pending',
            self::REFUND_PROCESSING => 'Processing',
            self::REFUND_REFUNDED => 'Refunded',
            self::REFUND_FAILED => 'Failed',
            default => ucfirst((string) $this->refund_status),
        };
    }

    public function isRefundable(): bool
    {
        return $this->type === self::TYPE_CANCELLATION
            && $this->status === self::STATUS_APPROVED
            && in_array($this->refund_status, [
                self::REFUND_PENDING,
                self::REFUND_FAILED,
            ], true);
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            self::TYPE_CANCELLATION => 'Cancellation',
            default => ucfirst((string) $this->type),
        };
    }
}
