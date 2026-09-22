<?php

namespace App\Observers;

use App\Models\ActivityLog;
use App\Models\Milestone;
use App\Models\Task;
use Illuminate\Support\Facades\Auth;

class TaskObserver
{
    public function created(Task $task): void
    {
        $this->recomputeMilestones($task);

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
        // Completing a task, or moving it between milestones, changes the
        // progress of every milestone involved.
        if ($task->wasChanged(['completed_at', 'milestone_id'])) {
            $this->recomputeMilestones($task);
        }

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
        $this->recomputeMilestones($task);

        $actor = Auth::user();

        ActivityLog::record(
            $task->workspace,
            $actor,
            'deleted',
            ($actor->name ?? 'Someone')." deleted task \"{$task->title}\"",
            $task,
        );
    }

    /**
     * Refresh the cached progress of the milestones this task affects — the one
     * it belongs to now and, when it was just moved, the one it came from.
     */
    protected function recomputeMilestones(Task $task): void
    {
        $ids = array_filter([
            $task->milestone_id,
            $task->getOriginal('milestone_id'),
        ]);

        if (! $ids) {
            return;
        }

        Milestone::query()
            ->whereIn('id', array_unique($ids))
            ->get()
            ->each(fn (Milestone $milestone) => $milestone->recomputeProgress());
    }
}
