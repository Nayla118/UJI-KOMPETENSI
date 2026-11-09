<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Destination extends Model
{
    use HasFactory;

    public const CATEGORY_PANTAI = 'pantai';

    public const CATEGORY_GUNUNG = 'gunung';

    public const CATEGORY_BUDAYA = 'budaya';

    public const CATEGORY_KULINER = 'kuliner';

    public const CATEGORY_ALAM = 'alam';

    public const CATEGORY_KOTA = 'kota';

    /**
     * Categories accepted by the catalog filter and the admin forms.
     */
    public const CATEGORIES = [
        self::CATEGORY_PANTAI,
        self::CATEGORY_GUNUNG,
        self::CATEGORY_BUDAYA,
        self::CATEGORY_KULINER,
        self::CATEGORY_ALAM,
        self::CATEGORY_KOTA,
    ];

    protected $fillable = [
        'name',
        'city',
        'country',
        'category',
        'description',
        'image',
        'rating',
        'is_popular',
        'latitude',
        'longitude',
    ];

    protected $casts = [
        'rating' => 'decimal:2',
        'is_popular' => 'boolean',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
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

    public function tourPackages(): HasMany
    {
        return $this->hasMany(TourPackage::class);
    }

    /**
     * Owners assigned to this destination (many-to-many via destination_user).
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'destination_user', 'destination_id', 'user_id')
            ->withTimestamps();
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * Gallery photos, always returned in display order.
     */
    public function images(): HasMany
    {
        return $this->hasMany(DestinationImage::class)->orderBy('sort_order');
    }

    /**
     * Only published reviews are allowed to influence public ratings.
     */
    public function publishedReviews(): HasMany
    {
        return $this->hasMany(Review::class)
            ->where('status', Review::STATUS_PUBLISHED);
    }
}
