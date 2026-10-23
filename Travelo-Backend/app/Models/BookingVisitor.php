<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * One traveller on a booking (the booker is also a visitor row).
 *
 * The full identity number and the private document path are hidden from
 * JSON by default. API responses expose a masked number and a document
 * status only.
 */
class BookingVisitor extends Model
{
    use HasFactory;

    public const TYPE_KTP = 'ktp';
    public const TYPE_KIA = 'kia';
    public const TYPE_STUDENT_CARD = 'student_card';
    public const TYPE_OTHER = 'other';

    public const TYPES = [
        self::TYPE_KTP,
        self::TYPE_KIA,
        self::TYPE_STUDENT_CARD,
        self::TYPE_OTHER,
    ];

    public const DOC_MISSING = 'missing';
    public const DOC_PENDING = 'pending';
    public const DOC_VERIFIED = 'verified';
    public const DOC_REJECTED = 'rejected';
    public const DOC_PURGED = 'purged';

    /** Only an explicit staff review may reach DOC_VERIFIED. */
    public const REVIEWABLE_STATUSES = [
        self::DOC_PENDING,
        self::DOC_VERIFIED,
        self::DOC_REJECTED,
    ];

    /** Statuses that mean a readable document is on file. */
    public const DOCUMENT_PRESENT_STATUSES = [
        self::DOC_PENDING,
        self::DOC_VERIFIED,
    ];

    protected $fillable = [
        'booking_id',
        'is_booker',
        'name',
        'identity_type',
        'identity_type_other',
        'identity_number',
        'date_of_birth',
        'phone',
        'document_path',
        'document_mime',
        'document_size',
        'document_status',
        'document_uploaded_at',
        'document_reviewed_at',
        'document_reviewed_by',
        'document_rejection_reason',
        'document_purged_at',
    ];

    protected $hidden = [
        // Never serialise raw identifiers or private storage paths.
        'identity_number',
        'document_path',
        'document_reviewed_by',
    ];

    protected $appends = [
        'identity_type_label',
        'identity_number_masked',
        'has_document',
        'is_identity_complete',
    ];

    protected $casts = [
        'is_booker' => 'boolean',
        'date_of_birth' => 'date:Y-m-d',
        'document_uploaded_at' => 'datetime',
        'document_reviewed_at' => 'datetime',
        'document_purged_at' => 'datetime',
        'document_size' => 'integer',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function getIdentityTypeLabelAttribute(): string
    {
        return match ($this->identity_type) {
            self::TYPE_KTP => 'KTP',
            self::TYPE_KIA => 'KIA (Kartu Identitas Anak)',
            self::TYPE_STUDENT_CARD => 'Kartu Pelajar',
            self::TYPE_OTHER => $this->identity_type_other ?: 'Identitas resmi lainnya',
            default => 'Identitas',
        };
    }

    /** Last four characters visible, e.g. "**************1234". */
    public function getIdentityNumberMaskedAttribute(): ?string
    {
        $number = (string) ($this->attributes['identity_number'] ?? '');

        if ($number === '') {
            return null;
        }

        if (strlen($number) <= 4) {
            return str_repeat('*', strlen($number));
        }

        return Str::mask($number, '*', 0, strlen($number) - 4);
    }

    public function getHasDocumentAttribute(): bool
    {
        return !empty($this->attributes['document_path'])
            && in_array($this->document_status, self::DOCUMENT_PRESENT_STATUSES, true);
    }

    /**
     * Complete = required identity data plus a document on file. This is
     * deliberately NOT called "verified": the document may still be pending
     * manual review.
     */
    public function getIsIdentityCompleteAttribute(): bool
    {
        if (trim((string) $this->name) === '') {
            return false;
        }

        if (!in_array($this->identity_type, self::TYPES, true)) {
            return false;
        }

        if (trim((string) ($this->attributes['identity_number'] ?? '')) === '') {
            return false;
        }

        if ($this->identity_type === self::TYPE_KIA && !$this->date_of_birth) {
            return false;
        }

        return $this->has_document;
    }

    public function isDocumentPending(): bool
    {
        return $this->document_status === self::DOC_PENDING;
    }

    public function isDocumentVerified(): bool
    {
        return $this->document_status === self::DOC_VERIFIED;
    }

    public function documentStatusLabel(): string
    {
        return match ($this->document_status) {
            self::DOC_PENDING => 'Menunggu pemeriksaan',
            self::DOC_VERIFIED => 'Dokumen diperiksa',
            self::DOC_REJECTED => 'Dokumen ditolak',
            self::DOC_PURGED => 'Dokumen dihapus (retensi)',
            default => 'Belum ada dokumen',
        };
    }
}
