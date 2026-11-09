<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\User;

/**
 * Booking access policy.
 *
 * Admin: every booking. Owner/manager: bookings for assigned destinations
 * (and only with bookings.view). Gate_staff: never. Traveller: their own.
 */
class BookingPolicy
{
    public function view(User $user, Booking $booking): bool
    {
        if (! $user->isActive()) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        if ($user->hasPermission('bookings.view')) {
            return $this->belongsToAssignedDestination($user, $booking);
        }

        // Travellers see only their own bookings.
        return $booking->user_id === $user->id;
    }

    public function manage(User $user, Booking $booking): bool
    {
        if (! $user->isActive()) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        if (! $user->hasPermission('change_requests.decide')) {
            return false;
        }

        return $this->belongsToAssignedDestination($user, $booking);
    }

    private function belongsToAssignedDestination(User $user, Booking $booking): bool
    {
        return $booking->tourPackage
            && $booking->tourPackage->destination
            && $user->destinations()->whereKey($booking->tourPackage->destination->id)->exists();
    }
}
