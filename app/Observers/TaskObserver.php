<?php

namespace App\Observers;

use App\Models\ActivityLog;
use App\Models\Task;
use Illuminate\Support\Facades\Auth;

class TaskObserver
{
    public function created(Task $task): void
    {
        $actor = Auth::user() ?? $task->creator;

        ActivityLog::record(
            $task->workspace,
            $actor,
            'created',
            ($actor->name ?? 'Someone')." created task \"{$task->title}\"",
            $task,
        );
    }

    public function updated(Task $task): void
    {
        $actor = Auth::user();
        if (! $actor) {
            return;
        }

        $changed = collect($task->getChanges())->except(['updated_at'])->keys()->all();
        if (! $changed) {
            return;
        }

        ActivityLog::record(
            $task->workspace,
            $actor,
            'updated',
            "{$actor->name} updated task \"{$task->title}\"",
            $task,
            ['changed' => $changed],
        );
    }

    public function deleted(Task $task): void
    {
        $actor = Auth::user();

        ActivityLog::record(
            $task->workspace,
            $actor,
            'deleted',
            ($actor->name ?? 'Someone')." deleted task \"{$task->title}\"",
            $task,
        );
    }
}
