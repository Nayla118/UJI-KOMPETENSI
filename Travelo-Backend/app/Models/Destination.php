<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Destination extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'city',
        'country',
        'description',
        'image',
        'rating',
        'is_popular',
    ];

    protected $casts = [
        'rating' => 'decimal:2',
        'is_popular' => 'boolean',
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

    public function tourPackages(): HasMany
    {
        return $this->hasMany(TourPackage::class);
    }
}
