<?php

namespace App\Http\Controllers\Milestones;

use App\Http\Controllers\Controller;
use App\Models\Milestone;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MilestoneController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Milestone::class);

        $workspaceId = $request->user()->currentWorkspace()->id;

        $data = $request->validate([
            'project_id' => ['required', Rule::exists('projects', 'id')->where('workspace_id', $workspaceId)],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'due_date' => ['nullable', 'date'],
            'progress' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        $project = Project::findOrFail($data['project_id']);
        $this->authorize('view', $project);

        Milestone::create($data + [
            'workspace_id' => $project->workspace_id,
            'created_by' => $request->user()->id,
        ]);

        return back()->with('flash.success', 'Milestone added.');
    }

    public function update(Request $request, Milestone $milestone): RedirectResponse
    {
        $this->authorize('update', $milestone);

        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'due_date' => ['nullable', 'date'],
            'progress' => ['nullable', 'integer', 'min:0', 'max:100'],
            'completed' => ['nullable', 'boolean'],
            'position' => ['nullable', 'integer', 'min:0'],
        ]);

        if (array_key_exists('completed', $data)) {
            $milestone->completed_at = $data['completed'] ? now() : null;
            if ($data['completed']) {
                $milestone->progress = 100;
            }
            unset($data['completed']);
        }

        $milestone->fill($data)->save();

        return back()->with('flash.success', 'Milestone updated.');
    }

    public function destroy(Milestone $milestone): RedirectResponse
    {
        $this->authorize('delete', $milestone);
        $milestone->delete();

        return back()->with('flash.success', 'Milestone removed.');
    }
}
