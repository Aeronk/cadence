<?php

namespace App\Http\Controllers\Milestones;

use App\Http\Controllers\Controller;
use App\Models\Milestone;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class MilestoneController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Milestone::class);

        $workspaceId = $request->user()->currentWorkspace()->id;

        $data = $request->validate([
            'project_id' => ['required', Rule::exists('projects', 'id')->where('workspace_id', $workspaceId)],
            // Linking a milestone to a goal is what makes goal progress roll up.
            'goal_id' => ['nullable', $this->goalRule($request)],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'due_date' => ['nullable', 'date'],
            'manual_progress' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        $project = Project::findOrFail($data['project_id']);
        $this->authorize('view', $project);

        $milestone = Milestone::create($data + [
            'workspace_id' => $project->workspace_id,
            'created_by' => $request->user()->id,
        ]);

        $milestone->recomputeProgress();

        return back()->with('flash.success', 'Milestone added.');
    }

    public function update(Request $request, Milestone $milestone): RedirectResponse
    {
        $this->authorize('update', $milestone);

        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'goal_id' => ['nullable', $this->goalRule($request)],
            'due_date' => ['nullable', 'date'],
            // Sending null hands progress back to the task count; sending a number
            // pins it there.
            'manual_progress' => ['nullable', 'integer', 'min:0', 'max:100'],
            'completed' => ['nullable', 'boolean'],
            'position' => ['nullable', 'integer', 'min:0'],
        ]);

        if (array_key_exists('completed', $data)) {
            $milestone->completed_at = $data['completed'] ? now() : null;
            // Marking it done pins progress at 100 rather than leaving the bar
            // disagreeing with the tick.
            if ($data['completed']) {
                $milestone->manual_progress = 100;
            }
            unset($data['completed']);
        }

        $milestone->fill($data)->save();
        $milestone->recomputeProgress();

        return back()->with('flash.success', 'Milestone updated.');
    }

    public function destroy(Milestone $milestone): RedirectResponse
    {
        $this->authorize('delete', $milestone);
        $milestone->delete();

        return back()->with('flash.success', 'Milestone removed.');
    }

    /**
     * A milestone may only be attached to a goal in the same workspace that
     * belongs to the person making the change — goals are personal.
     */
    protected function goalRule(Request $request): Exists
    {
        return Rule::exists('goals', 'id')
            ->where('workspace_id', $request->user()->currentWorkspace()->id)
            ->where('user_id', $request->user()->id)
            ->whereNull('deleted_at');
    }
}
