<?php

namespace App\Policies;

use App\Models\Meeting;
use App\Models\User;

class MeetingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->currentWorkspace() !== null;
    }

    public function view(User $user, Meeting $meeting): bool
    {
        if ($meeting->workspace->roleFor($user)?->canManageWorkspace()) {
            return true;
        }

        return $meeting->hasAttendee($user);
    }

    public function create(User $user): bool
    {
        return $user->currentWorkspace()?->hasMember($user) ?? false;
    }

    public function update(User $user, Meeting $meeting): bool
    {
        if ($meeting->workspace->roleFor($user)?->canManageWorkspace()) {
            return true;
        }

        return $meeting->host_id === $user->id;
    }

    public function delete(User $user, Meeting $meeting): bool
    {
        return $this->update($user, $meeting);
    }

    public function rsvp(User $user, Meeting $meeting): bool
    {
        return $meeting->hasAttendee($user);
    }
}
