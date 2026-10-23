<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    public const ROLE_TRAVELLER = 'traveller';
    public const ROLE_ADMIN = 'admin';
    public const ROLE_STAFF = 'staff';

    /**
     * Locally uploaded profile photos are stored as a relative path so the
     * public URL can follow APP_URL instead of being frozen at upload time.
     */
    public const PROFILE_PHOTO_DIRECTORY = 'profile-photos';

    protected $fillable = [
        'firebase_uid',
        'name',
        'email',
        'password',
        'role',
        'phone',
        'date_of_birth',
        'city',
        'photo',
        'api_token',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'api_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'date_of_birth' => 'date:Y-m-d',
        ];
    }

    /**
     * Provider photos are absolute URLs and are returned as-is. Locally
     * uploaded photos are stored as relative paths and resolved through the
     * public disk so the API always returns a usable URL.
     */
    public function getPhotoAttribute(?string $value): ?string
    {
        if (is_string($value) && str_starts_with($value, self::PROFILE_PHOTO_DIRECTORY.'/')) {
            return Storage::disk('public')->url($value);
        }

        return $value;
    }

    public function hasCustomPhoto(): bool
    {
        $photo = $this->getRawOriginal('photo');

        return is_string($photo)
            && str_starts_with($photo, self::PROFILE_PHOTO_DIRECTORY.'/');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * The booker's reusable identity. One row per account; created the first
     * time the traveller supplies visitor identity data.
     */
    public function savedIdentity(): HasOne
    {
        return $this->hasOne(SavedIdentity::class);
    }

    public function staffDestinations(): BelongsToMany
    {
        return $this->belongsToMany(Destination::class, 'staff_destinations', 'user_id', 'destination_id');
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isStaffOrAdmin(): bool
    {
        return in_array($this->role, [self::ROLE_ADMIN, self::ROLE_STAFF], true);
    }

    /**
     * Gate authorization. Admins cover every destination; staff only the
     * destinations explicitly assigned to them. Travellers never qualify.
     */
    public function canRedeemForDestination(?int $destinationId): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        if ($this->role !== self::ROLE_STAFF || $destinationId === null) {
            return false;
        }

        return $this->staffDestinations()->whereKey($destinationId)->exists();
    }
}
