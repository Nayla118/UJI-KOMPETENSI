<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TourPackage extends Model
{
    use HasFactory;

    protected $fillable = [
        'destination_id',
        'title',
        'description',
        'price',
        'duration_days',
        'max_people',
        'image',
    ];

    protected $casts = [
        'price' => 'decimal:2',
    ];

    /**
     * Get the full URL for the image
     */
    public function getImageUrlAttribute(): ?string
    {
        if (empty($this->image)) {
            return null;
        }

        // Check if it's already a full URL
        if (str_starts_with($this->image, 'http')) {
            return $this->image;
        }

        return asset('storage/' . $this->image);
    }

    /**
     * Get the image for API response
     */
    public function toArray(): array
    {
        $array = parent::toArray();
        $array['image'] = $this->imageUrl;
        return $array;
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(Destination::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}
