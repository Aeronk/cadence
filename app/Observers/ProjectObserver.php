<?php

namespace App\Observers;

use App\Models\ActivityLog;
use App\Models\Project;
use Illuminate\Support\Facades\Auth;

class ProjectObserver
{
    public function created(Project $project): void
    {
        $actor = Auth::user() ?? $project->creator;

        ActivityLog::record(
            $project->workspace,
            $actor,
            'created',
            ($actor->name ?? 'Someone')." created project \"{$project->title}\"",
            $project,
        );
    }

    public function updated(Project $project): void
    {
        $actor = Auth::user();
        if (! $actor) {
            return;
        }

        $changed = collect($project->getChanges())->except(['updated_at'])->keys()->all();
        if (! $changed) {
            return;
        }

        ActivityLog::record(
            $project->workspace,
            $actor,
            'updated',
            "{$actor->name} updated project \"{$project->title}\"",
            $project,
            ['changed' => $changed],
        );
    }

    public function deleted(Project $project): void
    {
        $actor = Auth::user();

        ActivityLog::record(
            $project->workspace,
            $actor,
            'deleted',
            ($actor->name ?? 'Someone')." deleted project \"{$project->title}\"",
            $project,
        );
    }
}
