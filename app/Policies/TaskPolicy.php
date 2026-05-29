<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->currentWorkspace() !== null;
    }

    public function view(User $user, Task $task): bool
    {
        if ($task->workspace->roleFor($user)?->canManageWorkspace()) {
            return true;
        }

        return $task->project->hasMember($user)
            || $task->assignees()->where('users.id', $user->id)->exists();
    }

    public function create(User $user, ?int $projectId = null): bool
    {
        $workspace = $user->currentWorkspace();

        return $workspace !== null && $workspace->hasMember($user);
    }

    public function update(User $user, Task $task): bool
    {
        if ($task->workspace->roleFor($user)?->canManageWorkspace()) {
            return true;
        }

        if ($task->created_by === $user->id) {
            return true;
        }

        return $task->assignees()->where('users.id', $user->id)->exists()
            || $task->project->hasMember($user);
    }

    public function delete(User $user, Task $task): bool
    {
        if ($task->workspace->roleFor($user)?->canManageWorkspace()) {
            return true;
        }

        return $task->created_by === $user->id;
    }

    public function complete(User $user, Task $task): bool
    {
        return $this->update($user, $task);
    }
}
