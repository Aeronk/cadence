<?php

namespace App\Policies;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;

class WorkspacePolicy
{
    public function view(User $user, Workspace $workspace): bool
    {
        return $workspace->hasMember($user);
    }

    public function update(User $user, Workspace $workspace): bool
    {
        return $workspace->roleFor($user)?->canManageWorkspace() ?? false;
    }

    public function delete(User $user, Workspace $workspace): bool
    {
        if ($workspace->is_personal) {
            return false;
        }

        return $workspace->roleFor($user)?->canDeleteWorkspace() ?? false;
    }

    public function inviteMember(User $user, Workspace $workspace): bool
    {
        return $workspace->roleFor($user)?->canManageWorkspace() ?? false;
    }

    public function removeMember(User $user, Workspace $workspace): bool
    {
        return $workspace->roleFor($user)?->canManageWorkspace() ?? false;
    }

    public function leave(User $user, Workspace $workspace): bool
    {
        $role = $workspace->roleFor($user);

        return $role !== null && $role !== WorkspaceRole::Owner;
    }
}
