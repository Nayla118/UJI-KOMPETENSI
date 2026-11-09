<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    public const ROLE_TRAVELLER = 'traveller';

    public const ROLE_ADMIN = 'admin';

    public const ROLE_OWNER = 'owner';

    public const ROLE_MANAGER = 'manager';

    public const ROLE_GATE_STAFF = 'gate_staff';

    /**
     * Roles that may sign in to the web portal. Travellers use the Android
     * app and never receive a web session.
     *
     * @var array<int, string>
     */
    public const PORTAL_ROLES = [
        self::ROLE_ADMIN,
        self::ROLE_OWNER,
        self::ROLE_MANAGER,
        self::ROLE_GATE_STAFF,
    ];

    /**
     * Staff roles an owner is allowed to create and manage.
     *
     * @var array<int, string>
     */
    public const OWNER_MANAGED_ROLES = [
        self::ROLE_MANAGER,
        self::ROLE_GATE_STAFF,
    ];

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
        'is_active',
        'created_by',
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
            'is_active' => 'boolean',
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

    public function deviceTokens(): HasMany
    {
        return $this->hasMany(DeviceToken::class);
    }

    public function changeRequests(): HasMany
    {
        return $this->hasMany(BookingChangeRequest::class);
    }

    public function wishlists(): HasMany
    {
        return $this->hasMany(Wishlist::class);
    }

    /**
     * The booker's reusable identity. One row per account; created the first
     * time the traveller supplies visitor identity data.
     */
    public function savedIdentity(): HasOne
    {
        return $this->hasOne(SavedIdentity::class);
    }

    /**
     * Destinations assigned to this owner (many-to-many via destination_user).
     * Admin is not limited by this relation; see canRedeemForDestination().
     */
    public function destinations(): BelongsToMany
    {
        return $this->belongsToMany(Destination::class, 'destination_user', 'user_id', 'destination_id')
            ->withTimestamps();
    }

    /**
     * Accounts are active unless explicitly deactivated. Existing rows and
     * accounts created without the attribute keep working (the database
     * default is true), which also keeps older tests/setup code valid.
     */
    public function isActive(): bool
    {
        return $this->is_active !== false;
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isOwner(): bool
    {
        return $this->role === self::ROLE_OWNER;
    }

    public function isManager(): bool
    {
        return $this->role === self::ROLE_MANAGER;
    }

    public function isGateStaff(): bool
    {
        return $this->role === self::ROLE_GATE_STAFF;
    }

    public function isStaffRole(): bool
    {
        return in_array($this->role, self::OWNER_MANAGED_ROLES, true);
    }

    public function isPortalUser(): bool
    {
        return in_array($this->role, self::PORTAL_ROLES, true);
    }

    public function isOwnerOrAdmin(): bool
    {
        return in_array($this->role, [self::ROLE_ADMIN, self::ROLE_OWNER], true);
    }

    /**
     * Permission map lookup. Admin is global (`*`); every other role must be
     * active and explicitly listed in config/permissions.php.
     */
    public function hasPermission(string $permission): bool
    {
        if (! $this->isActive()) {
            return false;
        }

        $permissions = (array) config('permissions.roles.'.$this->role, []);

        return in_array('*', $permissions, true)
            || in_array($permission, $permissions, true);
    }

    /**
     * Gate authorization. Admins cover every destination; owner, manager and
     * gate_staff only the destinations explicitly assigned to them. Everyone
     * must also hold tickets.redeem and be active. Travellers never qualify.
     */
    public function canRedeemForDestination(?int $destinationId): bool
    {
        if (! $this->hasPermission('tickets.redeem')) {
            return false;
        }

        if ($this->isAdmin()) {
            return true;
        }

        if (! in_array($this->role, [self::ROLE_OWNER, self::ROLE_MANAGER, self::ROLE_GATE_STAFF], true)
            || $destinationId === null) {
            return false;
        }

        return $this->destinations()->whereKey($destinationId)->exists();
    }

    /**
     * The account that created this staff member (owner/manager/gate_staff).
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(self::class, 'created_by');
    }

    /**
     * Staff accounts created by this user. Used by the admin owner list and
     * owner staff scoping.
     */
    public function createdStaff(): HasMany
    {
        return $this->hasMany(self::class, 'created_by');
    }

    /**
     * Deactivating an account must also kill every Sanctum token so a stolen
     * bearer token cannot keep using the API.
     */
    protected static function booted(): void
    {
        static::updated(function (User $user): void {
            if ($user->wasChanged('is_active') && ! $user->isActive()) {
                $user->tokens()->delete();
            }
        });
    }
}
