<?php

namespace App\Policies;

use App\Models\ProjectFile;
use App\Models\User;

class ProjectFilePolicy
{
    public function view(User $user, ProjectFile $file): bool
    {
        return $user->can('view', $file->project);
    }

    public function create(User $user): bool
    {
        return $user->currentWorkspace()?->hasMember($user) ?? false;
    }

    public function delete(User $user, ProjectFile $file): bool
    {
        return $file->uploaded_by === $user->id
            || ($file->project->workspace->roleFor($user)?->canManageWorkspace() ?? false);
    }
}
