<?php

namespace App\Policies;

use App\Models\Milestone;
use App\Models\User;

class MilestonePolicy
{
    public function view(User $user, Milestone $milestone): bool
    {
        return $this->allows($user, $milestone, 'view');
    }

    public function create(User $user): bool
    {
        return $user->currentWorkspace()?->hasMember($user) ?? false;
    }

    public function update(User $user, Milestone $milestone): bool
    {
        return $this->allows($user, $milestone, 'update');
    }

    public function delete(User $user, Milestone $milestone): bool
    {
        return $this->allows($user, $milestone, 'update');
    }

    /**
     * A milestone is governed by whatever it hangs off.
     *
     * Project milestones follow the project, as before. A milestone attached to
     * a goal alone has no project to ask, so it follows the goal — and goals are
     * personal, so only their owner reaches them. A milestone with neither is
     * orphaned and nobody may touch it.
     */
    protected function allows(User $user, Milestone $milestone, string $ability): bool
    {
        if ($milestone->project_id !== null) {
            return $milestone->project !== null
                && $user->can($ability, $milestone->project);
        }

        return $milestone->goal !== null
            && $user->can($ability, $milestone->goal);
    }
}
