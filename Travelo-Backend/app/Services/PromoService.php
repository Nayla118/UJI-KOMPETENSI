<?php

namespace App\Services;

use App\Exceptions\PromoValidationException;
use App\Models\Booking;
use App\Models\PromoCode;
use App\Models\PromoRedemption;
use App\Models\TourPackage;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Server-side promo validation and redemption.
 *
 * Clients never compute a discount: the API returns a preview and the booking
 * endpoint revalidates the code inside the same transaction that creates the
 * booking. `used_count` is changed with conditional SQL so concurrent
 * requests can never exceed `usage_limit`.
 */
class PromoService
{
    public const REASON_NOT_FOUND = 'not_found';

    public const REASON_INACTIVE = 'inactive';

    public const REASON_NOT_STARTED = 'not_started';

    public const REASON_EXPIRED = 'expired';

    public const REASON_QUOTA_EXHAUSTED = 'quota_exhausted';

    public const REASON_PER_USER_LIMIT = 'per_user_limit';

    public const REASON_MIN_TRANSACTION = 'min_transaction';

    public const REASON_NOT_APPLICABLE = 'not_applicable';

    /**
     * Validate a code against the current user, package, party size and date.
     *
     * @return array{
     *     ok: bool,
     *     reason?: string,
     *     message?: string,
     *     promo?: PromoCode,
     *     code?: string,
     *     subtotal: float,
     *     discount: float,
     *     total: float
     * }
     */
    public function validate(
        string $code,
        User $user,
        TourPackage $package,
        int $people,
        ?string $date = null,
        bool $lock = false
    ): array {
        $subtotal = $this->subtotal($package, $people);
        $normalized = PromoCode::normalizeCode($code);

        if ($normalized === '') {
            return $this->fail(self::REASON_NOT_FOUND, 'Enter a promo code.', $subtotal);
        }

        $query = PromoCode::query()->where('code', $normalized);

        if ($lock) {
            $query->lockForUpdate();
        }

        $promo = $query->first();

        if ($promo === null) {
            return $this->fail(self::REASON_NOT_FOUND, 'This promo code does not exist.', $subtotal);
        }

        if (! $promo->is_active) {
            return $this->fail(self::REASON_INACTIVE, 'This promo code is no longer active.', $subtotal);
        }

        if ($promo->starts_at !== null && $promo->starts_at->isFuture()) {
            return $this->fail(self::REASON_NOT_STARTED, 'This promo code is not active yet.', $subtotal);
        }

        if ($promo->expires_at !== null && $promo->expires_at->isPast()) {
            return $this->fail(self::REASON_EXPIRED, 'This promo code has expired.', $subtotal);
        }

        if ($promo->usage_limit !== null && (int) $promo->used_count >= (int) $promo->usage_limit) {
            return $this->fail(self::REASON_QUOTA_EXHAUSTED, 'This promo code has reached its usage limit.', $subtotal);
        }

        $usedByUser = $promo->activeRedemptions()
            ->where('user_id', $user->id)
            ->count();

        if ((int) $promo->per_user_limit > 0 && $usedByUser >= (int) $promo->per_user_limit) {
            return $this->fail(self::REASON_PER_USER_LIMIT, 'You have already used this promo code.', $subtotal);
        }

        if ($promo->tour_package_id !== null && (int) $promo->tour_package_id !== (int) $package->id) {
            return $this->fail(self::REASON_NOT_APPLICABLE, 'This promo code is not valid for this tour package.', $subtotal);
        }

        if ($promo->destination_id !== null && (int) $promo->destination_id !== (int) $package->destination_id) {
            return $this->fail(self::REASON_NOT_APPLICABLE, 'This promo code is not valid for this destination.', $subtotal);
        }

        if ($subtotal < (float) $promo->min_transaction) {
            return $this->fail(
                self::REASON_MIN_TRANSACTION,
                'This promo requires a minimum transaction of Rp '.number_format((float) $promo->min_transaction, 0, ',', '.').'.',
                $subtotal
            );
        }

        [$discount, $total] = $this->calculate($promo, $subtotal);

        return [
            'ok' => true,
            'promo' => $promo,
            'code' => $promo->code,
            'subtotal' => $subtotal,
            'discount' => $discount,
            'total' => $total,
            'discount_type' => $promo->discount_type,
            'discount_value' => (float) $promo->discount_value,
            'max_discount' => $promo->max_discount !== null ? (float) $promo->max_discount : null,
            'date' => $date,
        ];
    }

    /**
     * Percent discounts can be capped; fixed discounts are taken as-is.
     * The discount is always clamped to the subtotal so a total can never
     * become negative.
     *
     * @return array{0: float, 1: float}
     */
    public function calculate(PromoCode $promo, float $subtotal): array
    {
        $discount = $promo->isPercent()
            ? $subtotal * ((float) $promo->discount_value / 100)
            : (float) $promo->discount_value;

        if ($promo->isPercent() && $promo->max_discount !== null) {
            $discount = min($discount, (float) $promo->max_discount);
        }

        $discount = round(max(0, min($discount, $subtotal)), 2);
        $total = round(max(0, $subtotal - $discount), 2);

        return [$discount, $total];
    }

    /**
     * Consume one use and record the redemption for a booking. The
     * conditional UPDATE is the final guard against a quota race: even if two
     * transactions passed validation, only one can increment the last slot.
     */
    public function redeem(PromoCode $promo, User $user, Booking $booking, float $discount): PromoRedemption
    {
        $affected = PromoCode::query()
            ->whereKey($promo->id)
            ->where(function ($query) {
                $query->whereNull('usage_limit')
                    ->orWhereColumn('used_count', '<', 'usage_limit');
            })
            ->increment('used_count');

        if ($affected === 0) {
            throw new PromoValidationException(
                self::REASON_QUOTA_EXHAUSTED,
                'This promo code has reached its usage limit.'
            );
        }

        return PromoRedemption::create([
            'promo_code_id' => $promo->id,
            'user_id' => $user->id,
            'booking_id' => $booking->id,
            'discount_amount' => $discount,
            'status' => PromoRedemption::STATUS_APPLIED,
        ]);
    }

    /**
     * Idempotently return a redeemed promo to the pool when its booking is
     * cancelled/expired. A second call is a no-op and can never make
     * `used_count` negative.
     */
    public function releaseForBooking(Booking $booking): bool
    {
        return DB::transaction(function () use ($booking) {
            $redemption = PromoRedemption::query()
                ->where('booking_id', $booking->id)
                ->first();

            if ($redemption === null || $redemption->status === PromoRedemption::STATUS_RELEASED) {
                return false;
            }

            // Lock the promo first so concurrent bookings for the same code
            // serialize their counter updates. Deleted promos stay reachable
            // here so a cancelled booking can still settle its counter.
            PromoCode::withTrashed()
                ->whereKey($redemption->promo_code_id)
                ->lockForUpdate()
                ->first();

            $locked = PromoRedemption::query()
                ->whereKey($redemption->id)
                ->lockForUpdate()
                ->first();

            if ($locked === null || $locked->status === PromoRedemption::STATUS_RELEASED) {
                return false;
            }

            $locked->status = PromoRedemption::STATUS_RELEASED;
            $locked->save();

            PromoCode::withTrashed()
                ->whereKey($locked->promo_code_id)
                ->where('used_count', '>', 0)
                ->decrement('used_count');

            return true;
        });
    }

    /**
     * Re-apply a released redemption when a late payment revives an expired
     * booking. The customer already paid the discounted total, so the use is
     * restored even if the code has since reached its quota (the counter may
     * exceed the limit by one in that rare case; the alternative is charging
     * a different amount than the one paid).
     */
    public function restoreForBooking(Booking $booking): bool
    {
        if ($booking->promo_code_id === null) {
            return false;
        }

        return DB::transaction(function () use ($booking) {
            $redemption = PromoRedemption::query()
                ->where('booking_id', $booking->id)
                ->first();

            if ($redemption === null) {
                return false;
            }

            PromoCode::withTrashed()
                ->whereKey($redemption->promo_code_id)
                ->lockForUpdate()
                ->first();

            $locked = PromoRedemption::query()
                ->whereKey($redemption->id)
                ->lockForUpdate()
                ->first();

            if ($locked === null || $locked->status === PromoRedemption::STATUS_APPLIED) {
                return false;
            }

            $locked->status = PromoRedemption::STATUS_APPLIED;
            $locked->save();

            PromoCode::withTrashed()
                ->whereKey($locked->promo_code_id)
                ->increment('used_count');

            return true;
        });
    }

    public function subtotal(TourPackage $package, int $people): float
    {
        return round((float) $package->price * max(1, $people), 2);
    }

    /**
     * @return array{ok: false, reason: string, message: string, subtotal: float, discount: float, total: float}
     */
    private function fail(string $reason, string $message, float $subtotal): array
    {
        return [
            'ok' => false,
            'reason' => $reason,
            'message' => $message,
            'subtotal' => $subtotal,
            'discount' => 0.0,
            'total' => $subtotal,
        ];
    }

    /**
     * Exposed for admin previews/tests: whether the current moment falls
     * inside the promo window.
     */
    public function isWithinWindow(PromoCode $promo, ?CarbonInterface $moment = null): bool
    {
        $moment ??= now();

        if ($promo->starts_at !== null && $promo->starts_at->isAfter($moment)) {
            return false;
        }

        return ! ($promo->expires_at !== null && $promo->expires_at->isBefore($moment));
    }
}
