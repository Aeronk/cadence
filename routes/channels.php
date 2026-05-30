<?php

use App\Models\Project;
use App\Models\Task;
use App\Models\Workspace;
use Illuminate\Support\Facades\Broadcast;

// Per-user notification channel — Laravel notifications use this name by default.
Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Workspace activity feed — anyone who is a member can subscribe.
Broadcast::channel('workspace.{workspaceId}', function ($user, int $workspaceId) {
    $workspace = Workspace::find($workspaceId);

    return $workspace && $workspace->hasMember($user);
});

// Per-project channel for live comments / task updates on the project's board.
Broadcast::channel('project.{projectId}', function ($user, int $projectId) {
    $project = Project::find($projectId);

    return $project && $user->can('view', $project);
});

// Per-task channel for live comments on the task detail page.
Broadcast::channel('task.{taskId}', function ($user, int $taskId) {
    $task = Task::find($taskId);

    return $task && $user->can('view', $task);
});
