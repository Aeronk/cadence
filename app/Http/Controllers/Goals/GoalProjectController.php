<?php

namespace App\Http\Controllers\Goals;

use App\Http\Controllers\Controller;
use App\Models\Goal;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Attaching a project to a goal is what turns "we are busy" into "we are making
 * progress against something".
 *
 * Reachable from both ends — the goal page picks projects, the project page
 * picks goals — because people arrive from either direction.
 */
class GoalProjectController extends Controller
{
    public function store(Request $request, Goal $goal): RedirectResponse
    {
        // Goals are personal, so authority over the link comes from the goal.
        $this->authorize('update', $goal);

        $data = $request->validate([
            'project_id' => [
                'required',
                Rule::exists('projects', 'id')
                    ->where('workspace_id', $goal->workspace_id)
                    ->whereNull('deleted_at'),
            ],
        ]);

        $project = Project::findOrFail($data['project_id']);
        // Linking exposes the project's progress on the goal page, so the person
        // doing it has to be allowed to see the project in the first place.
        $this->authorize('view', $project);

        // syncWithoutDetaching rather than attach: linking twice is a harmless
        // thing for someone to do and should not raise a unique-key error.
        $goal->projects()->syncWithoutDetaching([$project->id]);

        return back()->with('flash.success', "\"{$project->title}\" now counts towards this goal.");
    }

    public function destroy(Goal $goal, Project $project): RedirectResponse
    {
        $this->authorize('update', $goal);

        $goal->projects()->detach($project->id);

        return back()->with('flash.success', 'Project unlinked. Nothing was deleted.');
    }
}
