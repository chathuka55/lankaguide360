<?php

namespace App\Policies;

use App\Models\Trip;
use App\Models\User;

/**
 * Travellers see only their own trips (guests use the private token link instead);
 * agents and admins work on every trip request.
 */
class TripPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff();
    }

    public function view(User $user, Trip $trip): bool
    {
        return $user->isStaff() || $trip->user_id === $user->id;
    }

    /** Agents/admins edit the plan and price while reviewing. */
    public function update(User $user, Trip $trip): bool
    {
        return $user->isStaff();
    }

    public function cancel(User $user, Trip $trip): bool
    {
        return $trip->user_id === $user->id && $trip->status->travellerCanCancel();
    }

    public function review(User $user, Trip $trip): bool
    {
        return $trip->user_id === $user->id && $trip->status->value === 'completed';
    }
}
