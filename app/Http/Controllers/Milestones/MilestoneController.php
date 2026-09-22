<?php

namespace App\Http\Controllers\Milestones;

use App\Http\Controllers\Controller;
use App\Models\Goal;
use App\Models\Milestone;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\ValidationException;

class MilestoneController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Milestone::class);

        $workspaceId = $request->user()->currentWorkspace()->id;

        $data = $request->validate([
            // A milestone belongs to a project, a goal, or both. Requiring a
            // project forced people to invent throwaway projects to record a
            // personal checkpoint, so either anchor is now enough.
            'project_id' => [
                'nullable',
                Rule::exists('projects', 'id')->where('workspace_id', $workspaceId),
            ],
            // Linking a milestone to a goal is what makes goal progress roll up.
            'goal_id' => ['nullable', $this->goalRule($request)],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'due_date' => ['nullable', 'date'],
            'manual_progress' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        if (empty($data['project_id']) && empty($data['goal_id'])) {
            throw ValidationException::withMessages([
                'project_id' => 'A milestone needs either a project or a goal to belong to.',
            ]);
        }

        // Being in the workspace is not the same as being allowed near the
        // project, so the project itself still has to grant it.
        if (! empty($data['project_id'])) {
            $this->authorize('update', Project::findOrFail($data['project_id']));
        }

        $workspaceId = $this->resolveWorkspace($data, $workspaceId);

        $milestone = Milestone::create($data + [
            'workspace_id' => $workspaceId,
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

        // Unlinking a milestone that has no project would leave it belonging to
        // nothing and reachable by nobody — the policy has no owner to ask.
        if (array_key_exists('goal_id', $data)
            && $data['goal_id'] === null
            && $milestone->project_id === null) {
            throw ValidationException::withMessages([
                'goal_id' => 'This milestone has no project, so it cannot be unlinked from its goal. Delete it instead.',
            ]);
        }

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
     * The workspace the milestone lands in: the project's if there is one, else
     * the goal's. Both were already checked to be in the caller's workspace, so
     * this only picks which record to read it from.
     *
     * @param  array<string, mixed>  $data
     */
    protected function resolveWorkspace(array $data, int $fallback): int
    {
        if (! empty($data['project_id'])) {
            return (int) Project::query()->whereKey($data['project_id'])->value('workspace_id');
        }

        if (! empty($data['goal_id'])) {
            return (int) Goal::query()->whereKey($data['goal_id'])->value('workspace_id');
        }

        return $fallback;
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
