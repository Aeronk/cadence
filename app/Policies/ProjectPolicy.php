<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->currentWorkspace() !== null;
    }

    public function view(User $user, Project $project): bool
    {
        if (! $project->workspace->hasMember($user)) {
            return false;
        }

        if ($project->workspace->roleFor($user)?->canManageWorkspace()) {
            return true;
        }

        return $project->hasMember($user);
    }

    public function create(User $user): bool
    {
        return $user->currentWorkspace()?->hasMember($user) ?? false;
    }

    public function update(User $user, Project $project): bool
    {
        if ($project->workspace->roleFor($user)?->canManageWorkspace()) {
            return true;
        }

        return $project->created_by === $user->id;
    }

    public function delete(User $user, Project $project): bool
    {
        if ($project->workspace->roleFor($user)?->canManageWorkspace()) {
            return true;
        }

        return $project->created_by === $user->id;
    }

    public function archive(User $user, Project $project): bool
    {
        return $this->update($user, $project);
    }

    public function manageMembers(User $user, Project $project): bool
    {
        return $this->update($user, $project);
    }
}
