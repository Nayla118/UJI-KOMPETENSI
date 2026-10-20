<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

/**
 * One row = one OTP issued for an email login attempt.
 * Codes are stored hashed, expire after a few minutes, are single-use and
 * invalidated after too many wrong guesses.
 */
class LoginOtp extends Model
{
    public const TTL_MINUTES = 5;
    public const MAX_ATTEMPTS = 5;
    public const RESEND_COOLDOWN_SECONDS = 60;

    protected $fillable = [
        'email',
        'firebase_uid',
        'code_hash',
        'attempts',
        'expires_at',
        'verified_at',
        'ip',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'verified_at' => 'datetime',
        'attempts' => 'integer',
    ];

    /** The newest, still-active (unverified, unexpired) code for an email. */
    public function scopeActiveForEmail(Builder $query, string $email): Builder
    {
        return $query
            ->where('email', $email)
            ->whereNull('verified_at')
            ->where('expires_at', '>', now())
            ->orderByDesc('id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at === null || $this->expires_at->isPast();
    }

    public function hasExceededAttempts(): bool
    {
        return $this->attempts >= self::MAX_ATTEMPTS;
    }

    /** Seconds until a resend is allowed (0 when the cooldown is over). */
    public function resendCooldownRemaining(): int
    {
        $readyAt = $this->created_at?->copy()->addSeconds(self::RESEND_COOLDOWN_SECONDS);

        if ($readyAt === null) {
            return 0;
        }

        $remaining = $readyAt->getTimestamp() - now()->getTimestamp();

        return $remaining > 0 ? (int) $remaining : 0;
    }
}
