<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TourPackageImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'tour_package_id',
        'path',
        'caption',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    /**
     * Public URL for the gallery photo. Seeded demo rows may store an absolute
     * URL, real uploads store a path on the public disk.
     */
    public function getUrlAttribute(): ?string
    {
        if (empty($this->path)) {
            return null;
        }

        if (str_starts_with($this->path, 'http://') || str_starts_with($this->path, 'https://')) {
            return $this->path;
        }

        return asset('storage/'.$this->path);
    }

    public function tourPackage(): BelongsTo
    {
        return $this->belongsTo(TourPackage::class);
    }
}
