<?php

namespace App\Policies;

use App\Models\Milestone;
use App\Models\User;

class MilestonePolicy
{
    public function view(User $user, Milestone $milestone): bool
    {
        return $user->can('view', $milestone->project);
    }

    public function create(User $user): bool
    {
        return $user->currentWorkspace()?->hasMember($user) ?? false;
    }

    public function update(User $user, Milestone $milestone): bool
    {
        return $user->can('update', $milestone->project);
    }

    public function delete(User $user, Milestone $milestone): bool
    {
        return $user->can('update', $milestone->project);
    }
}
