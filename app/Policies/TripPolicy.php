<?php

namespace App\Policies;

use App\Models\Trip;
use App\Models\User;

class TripPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->currentWorkspace() !== null;
    }

    public function view(User $user, Trip $trip): bool
    {
        return $trip->user_id === $user->id
            || ($trip->workspace->roleFor($user)?->canManageWorkspace() ?? false);
    }

    public function create(User $user): bool
    {
        return $user->currentWorkspace()?->hasMember($user) ?? false;
    }

    public function update(User $user, Trip $trip): bool
    {
        return $trip->user_id === $user->id
            || ($trip->workspace->roleFor($user)?->canManageWorkspace() ?? false);
    }

    public function delete(User $user, Trip $trip): bool
    {
        return $this->update($user, $trip);
    }
}
