<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The booker's reusable identity for one account.
 *
 * Raw identity numbers and private document paths are hidden from JSON by
 * default. The dedicated owner-scoped endpoint exposes only the fields needed
 * to prefill the booking form and never exposes a storage path.
 */
class SavedIdentity extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
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
    ];

    protected $hidden = [
        'identity_number',
        'document_path',
        'document_reviewed_by',
    ];

    protected $appends = [
        'has_document',
        'can_reuse_document',
    ];

    protected $casts = [
        'date_of_birth' => 'date:Y-m-d',
        'document_uploaded_at' => 'datetime',
        'document_reviewed_at' => 'datetime',
        'document_size' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** A readable document is on file and has not been rejected. */
    public function getHasDocumentAttribute(): bool
    {
        return !empty($this->attributes['document_path'])
            && in_array($this->document_status, BookingVisitor::DOCUMENT_PRESENT_STATUSES, true);
    }

    /**
     * The next booking may copy this file. A rejected document is kept only
     * until the traveller uploads a replacement.
     */
    public function getCanReuseDocumentAttribute(): bool
    {
        return $this->has_document;
    }
}
