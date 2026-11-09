<?php

namespace App\Observers;

use App\Models\Booking;
use App\Services\PromoService;

/**
 * Cancelling or expiring a booking must return its promo quota exactly once.
 * Centralising the hook here covers every transition point (admin status
 * update, approved cancellation request, Midtrans expiry/failure).
 */
class BookingObserver
{
    public function __construct(private PromoService $promos) {}

    public function updated(Booking $booking): void
    {
        if (! $booking->wasChanged(['booking_status', 'payment_status'])) {
            return;
        }

        $cancelled = $booking->booking_status === 'cancelled';
        $paymentDead = in_array($booking->payment_status, ['failed', 'expired'], true);

        if ($cancelled || $paymentDead) {
            $this->promos->releaseForBooking($booking);
        }
    }
}
