<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\User;

/**
 * Ticket redemption (scanner) access policy.
 *
 * Admin: every destination. Owner/manager/gate_staff: only assigned
 * destinations and only with tickets.redeem. Traveller: never.
 */
class TicketPolicy
{
    public function redeem(User $user, ?int $destinationId = null): bool
    {
        return $user->canRedeemForDestination($destinationId);
    }

    public function redeemForBooking(User $user, Booking $booking): bool
    {
        return $user->canRedeemForDestination(
            $booking->tourPackage?->destination_id
        );
    }
}
