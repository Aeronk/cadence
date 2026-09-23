<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Project;
use Illuminate\Http\Request;

/**
 * The list of projects a record can be filed under.
 *
 * Shared so that meetings, notes, to-dos and trips all offer the same set and
 * order — an archived project appearing in one picker but not another is the
 * sort of inconsistency nobody reports but everybody notices.
 */
trait OffersProjectOptions
{
    /**
     * @return array<int, array{id: int, title: string}>
     */
    protected function projectOptions(Request $request): array
    {
        $workspace = $request->user()?->currentWorkspace();

        if ($workspace === null) {
            return [];
        }

        return Project::query()
            ->forWorkspace($workspace)
            // Archived projects are done with; offering them invites filing new
            // work against something nobody is looking at any more.
            ->whereNull('archived_at')
            ->orderBy('title')
            ->get(['id', 'title'])
            ->map(fn (Project $p) => ['id' => $p->id, 'title' => $p->title])
            ->all();
    }
}
