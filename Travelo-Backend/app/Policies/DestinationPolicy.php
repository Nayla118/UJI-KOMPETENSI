<?php

namespace App\Policies;

use App\Models\Destination;
use App\Models\User;

/**
 * Destination access policy.
 *
 * Admin: every destination. Owner/manager/gate_staff: only destinations
 * assigned via destination_user pivot (and only the permission they hold).
 * Traveller: never.
 */
class DestinationPolicy
{
    public function view(User $user, Destination $destination): bool
    {
        return $this->canAccess($user, $destination->id, 'destinations.view');
    }

    public function manage(User $user, Destination $destination): bool
    {
        return $this->canAccess($user, $destination->id, 'destinations.update');
    }

    public function scan(User $user, Destination $destination): bool
    {
        return $this->canAccess($user, $destination->id, 'tickets.redeem');
    }

    private function canAccess(User $user, int $destinationId, string $permission): bool
    {
        if (! $user->isActive()) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        if (! $user->hasPermission($permission)) {
            return false;
        }

        return $user->destinations()->whereKey($destinationId)->exists();
    }
}
