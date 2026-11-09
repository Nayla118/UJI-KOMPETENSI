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
        'inclusions',
        'exclusions',
        'meeting_point',
        'meeting_latitude',
        'meeting_longitude',
        'terms',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'inclusions' => 'array',
        'exclusions' => 'array',
        'meeting_latitude' => 'decimal:7',
        'meeting_longitude' => 'decimal:7',
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

        return asset('storage/'.$this->image);
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

    /**
     * Explicit per-date capacity/closed overrides. Dates without a row use
     * `max_people` as their capacity.
     */
    public function dateAvailabilities(): HasMany
    {
        return $this->hasMany(TourPackageDateAvailability::class);
    }

    /**
     * Day-by-day itinerary, always returned in display order.
     */
    public function itineraryDays(): HasMany
    {
        return $this->hasMany(TourPackageItineraryDay::class)
            ->orderBy('sort_order')
            ->orderBy('day_number');
    }

    /**
     * Gallery photos, always returned in display order.
     */
    public function images(): HasMany
    {
        return $this->hasMany(TourPackageImage::class)->orderBy('sort_order');
    }
}
