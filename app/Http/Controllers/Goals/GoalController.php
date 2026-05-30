<?php

namespace App\Http\Controllers\Goals;

use App\Http\Controllers\Controller;
use App\Models\Goal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class GoalController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Goal::class);

        $user = $request->user();
        $workspace = $user->currentWorkspace();

        $goals = Goal::query()
            ->forWorkspace($workspace)
            ->where('user_id', $user->id)
            ->with(['children.children', 'milestones:id,goal_id,progress'])
            ->orderBy('title')
            ->get();

        // Compute progress server-side so the UI is light.
        $payload = $goals->map(function (Goal $g) {
            return [
                'id' => $g->id,
                'parent_id' => $g->parent_id,
                'type' => $g->type,
                'title' => $g->title,
                'description' => $g->description,
                'horizon' => $g->horizon,
                'target_date' => $g->target_date?->toDateString(),
                'progress' => $g->computedProgress(),
                'completed_at' => $g->completed_at?->toIso8601String(),
                'milestones_count' => $g->milestones->count(),
            ];
        });

        return Inertia::render('Goals/Index', ['goals' => $payload]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Goal::class);

        $data = $request->validate([
            'parent_id' => ['nullable', 'exists:goals,id'],
            'type' => ['nullable', Rule::in(['vision', 'goal', 'objective'])],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'horizon' => ['nullable', Rule::in(['year', 'quarter', 'month'])],
            'target_date' => ['nullable', 'date'],
            'progress' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        Goal::create($data + [
            'workspace_id' => $request->user()->currentWorkspace()->id,
            'user_id' => $request->user()->id,
            'type' => $data['type'] ?? 'goal',
        ]);

        return back()->with('flash.success', 'Goal added.');
    }

    public function update(Request $request, Goal $goal): RedirectResponse
    {
        $this->authorize('update', $goal);

        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'horizon' => ['nullable', Rule::in(['year', 'quarter', 'month'])],
            'target_date' => ['nullable', 'date'],
            'progress' => ['nullable', 'integer', 'min:0', 'max:100'],
            'completed' => ['nullable', 'boolean'],
        ]);

        if (array_key_exists('completed', $data)) {
            $goal->completed_at = $data['completed'] ? now() : null;
            if ($data['completed']) $goal->progress = 100;
            unset($data['completed']);
        }

        $goal->fill($data)->save();

        return back()->with('flash.success', 'Goal updated.');
    }

    public function destroy(Goal $goal): RedirectResponse
    {
        $this->authorize('delete', $goal);
        $goal->delete();

        return back()->with('flash.success', 'Goal removed.');
    }
}
