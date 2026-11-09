<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Releases seats and promo quota for bookings whose payment window ran out.
 *
 * Every booking is processed in its own transaction with a row lock and a
 * fresh status check. A Midtrans webhook that already marked the booking paid
 * therefore wins: the command skips it instead of cancelling a paid booking.
 */
class BookingExpiryService
{
    public const RESULT_EXPIRED = 'expired';

    public const RESULT_SKIPPED = 'skipped';

    public const RESULT_MISSING = 'missing';

    public function __construct(
        private MidtransService $midtrans,
        private PushNotificationService $push
    ) {}

    /**
     * @return array{expired: int, skipped: int}
     */
    public function expireDue(): array
    {
        $expired = 0;
        $skipped = 0;

        Booking::query()
            ->where('booking_status', 'pending')
            ->whereNotIn('payment_status', ['paid', 'failed', 'expired'])
            ->whereNotNull('payment_expires_at')
            ->where('payment_expires_at', '<=', now())
            ->orderBy('id')
            ->chunkById(100, function ($bookings) use (&$expired, &$skipped) {
                foreach ($bookings as $booking) {
                    $result = $this->expireBooking((int) $booking->id);

                    if ($result === self::RESULT_EXPIRED) {
                        $expired++;
                    } else {
                        $skipped++;
                    }
                }
            });

        return ['expired' => $expired, 'skipped' => $skipped];
    }

    /**
     * Idempotent: a second call finds a cancelled/expired booking and skips.
     * The observer on Booking releases promo quota when the status changes.
     */
    public function expireBooking(int $bookingId): string
    {
        $orderId = null;

        $result = DB::transaction(function () use ($bookingId, &$orderId) {
            $booking = Booking::query()
                ->whereKey($bookingId)
                ->lockForUpdate()
                ->first();

            if ($booking === null) {
                return self::RESULT_MISSING;
            }

            if ($booking->payment_status === Payment::STATUS_PAID
                || $booking->booking_status !== 'pending'
                || ! $booking->paymentWindowExpired()) {
                return self::RESULT_SKIPPED;
            }

            $payment = Payment::query()
                ->where('booking_id', $booking->id)
                ->lockForUpdate()
                ->first();

            // A webhook may have written the payment row first even when the
            // booking row still looked pending a moment ago.
            if ($payment?->payment_status === Payment::STATUS_PAID) {
                return self::RESULT_SKIPPED;
            }

            $orderId = $payment?->midtrans_order_id;

            if ($payment !== null) {
                $payment->update([
                    'payment_status' => Payment::STATUS_EXPIRED,
                ]);
            }

            // Cancelled is the project-wide convention for an unpaid/expired
            // booking (Midtrans "expire" already maps to the same state).
            // "expired" stays on payment_status, which also releases the seats
            // in AvailabilityService.
            $booking->update([
                'payment_status' => 'expired',
                'booking_status' => 'cancelled',
            ]);

            ActivityLog::log(
                'Booking payment expired',
                Booking::class,
                $booking->id,
                null,
                null,
                [
                    'booking_id' => $booking->id,
                    'payment_expires_at' => $booking->payment_expires_at?->toIso8601String(),
                    'source' => 'travelo:expire-unpaid-bookings',
                ]
            );

            return self::RESULT_EXPIRED;
        });

        if ($result !== self::RESULT_EXPIRED) {
            return $result;
        }

        $booking = Booking::query()
            ->with(['tourPackage.destination', 'payment'])
            ->find($bookingId);

        if ($booking === null) {
            return $result;
        }

        // Best effort: never let Midtrans availability/network decide whether
        // the local seat release succeeds. Config can disable it entirely.
        if ($orderId !== null
            && $orderId !== ''
            && (bool) config('travelo.payment.expire_midtrans_transaction', true)) {
            try {
                $this->midtrans->expireTransaction($orderId);
            } catch (\Throwable $e) {
                Log::warning('Midtrans expire failed (local expiry kept)', [
                    'booking_id' => $booking->id,
                    'order_id' => $orderId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        try {
            $this->push->notifyBookingExpired($booking);
        } catch (\Throwable $e) {
            Log::warning('Booking-expired push notification failed', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $result;
    }
}
