<?php

namespace App\Http\Controllers\Goals;

use App\Http\Controllers\Controller;
use App\Models\Goal;
use App\Models\Milestone;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class GoalController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Goal::class);

        $workspace = $request->user()->currentWorkspace();

        $goals = $this->ownedGoals($request)
            ->with([
                'children',
                'milestones' => fn ($q) => $q->with('project:id,title')->orderBy('due_date'),
                // computedProgress() reads each project's own progress, which is
                // itself read from its milestones — loaded here so a goal tree
                // does not fire a query per project per node.
                'projects' => fn ($q) => $q->with('milestones:id,project_id,progress')
                    // A project with no milestones falls back to counting tasks;
                    // loading those counts here keeps it to one query overall.
                    ->withCount([
                        'tasks',
                        'tasks as completed_tasks_count' => fn ($q) => $q->whereNotNull('completed_at'),
                    ])
                    ->orderBy('title'),
            ])
            ->orderBy('position')
            ->orderBy('title')
            ->get();

        // computedProgress() walks children, so the whole set is fetched once and
        // the relation wired up in memory. Eager loading a fixed two levels left
        // the third level firing a query per node.
        $this->linkChildren($goals);

        return Inertia::render('Goals/Index', [
            'goals' => $goals->map(fn (Goal $g) => $this->goalPayload($g))->values(),
            'stats' => $this->stats($goals),
            // Milestones in this workspace not yet attached to a goal, so one can
            // be linked without leaving the page.
            'linkable_milestones' => Milestone::query()
                ->forWorkspace($workspace)
                ->whereNull('goal_id')
                ->with('project:id,title')
                ->orderBy('title')
                ->get(['id', 'title', 'project_id', 'progress'])
                ->map(fn (Milestone $m) => [
                    'id' => $m->id,
                    'title' => $m->title,
                    'progress' => (int) $m->progress,
                    'project_title' => $m->project?->title,
                ])
                ->values(),
            'projects' => $this->projectOptions($request),
        ]);
    }

    public function show(Request $request, Goal $goal): Response
    {
        $this->authorize('view', $goal);

        $goal->load('parent:id,title,type');

        // The whole subtree, so the page can show rolled-up progress rather than
        // only what the goal's immediate children happen to report.
        $subtree = $this->ownedGoals($request)
            ->whereIn('id', $goal->descendantIds())
            ->with([
                'children',
                'milestones' => fn ($q) => $q->with('project:id,title')->orderBy('position'),
                'projects' => fn ($q) => $q->with('milestones:id,project_id,progress')
                    // A project with no milestones falls back to counting tasks;
                    // loading those counts here keeps it to one query overall.
                    ->withCount([
                        'tasks',
                        'tasks as completed_tasks_count' => fn ($q) => $q->whereNotNull('completed_at'),
                    ])
                    ->orderBy('title'),
            ])
            ->orderBy('position')
            ->orderBy('title')
            ->get();

        $this->linkChildren($subtree);

        $resolved = $subtree->firstWhere('id', $goal->id) ?? $goal;
        $resolved->setRelation('parent', $goal->parent);

        return Inertia::render('Goals/Show', [
            'goal' => $this->goalPayload($resolved) + [
                'parent' => $goal->parent ? [
                    'id' => $goal->parent->id,
                    'title' => $goal->parent->title,
                    'type' => $goal->parent->type,
                    'url' => route('goals.show', $goal->parent->id),
                ] : null,
            ],
            'children' => $resolved->children
                ->map(fn (Goal $c) => $this->goalPayload($c))
                ->values(),
            // Reparenting options, minus this goal's own subtree: moving a goal
            // under one of its descendants would cut the branch loose entirely.
            'parent_options' => $this->ownedGoals($request)
                ->whereNotIn('id', $goal->descendantIds())
                ->orderBy('title')
                ->get(['id', 'title', 'type'])
                ->map(fn (Goal $g) => ['id' => $g->id, 'title' => $g->title, 'type' => $g->type])
                ->values(),
            'projects' => $this->projectOptions($request),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Goal::class);

        $data = $request->validate([
            'parent_id' => ['nullable', 'integer', $this->goalExistsRule($request)],
            'type' => ['nullable', Rule::in(Goal::TYPES)],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'horizon' => ['nullable', Rule::in(Goal::HORIZONS)],
            'status' => ['nullable', Rule::in(Goal::STATUSES)],
            'target_date' => ['nullable', 'date'],
            'progress' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        Goal::create($data + [
            'workspace_id' => $request->user()->currentWorkspace()->id,
            'user_id' => $request->user()->id,
            'type' => $data['type'] ?? Goal::TYPE_GOAL,
            'status' => $data['status'] ?? Goal::STATUS_ON_TRACK,
            'position' => $this->nextPosition($request, $data['parent_id'] ?? null),
        ]);

        return back()->with('flash.success', 'Goal added.');
    }

    public function update(Request $request, Goal $goal): RedirectResponse
    {
        $this->authorize('update', $goal);

        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['sometimes', Rule::in(Goal::TYPES)],
            'parent_id' => ['nullable', 'integer', $this->goalExistsRule($request)],
            'horizon' => ['nullable', Rule::in(Goal::HORIZONS)],
            'status' => ['sometimes', Rule::in(Goal::STATUSES)],
            'target_date' => ['nullable', 'date'],
            'progress' => ['nullable', 'integer', 'min:0', 'max:100'],
            'position' => ['nullable', 'integer', 'min:0'],
            'completed' => ['nullable', 'boolean'],
        ]);

        // Rejected rather than quietly dropped: someone who picked this parent
        // needs to know the move did not happen.
        if (! empty($data['parent_id']) && in_array((int) $data['parent_id'], $goal->descendantIds(), true)) {
            throw ValidationException::withMessages([
                'parent_id' => 'A goal cannot sit under itself or one of its own sub-goals.',
            ]);
        }

        if (array_key_exists('completed', $data)) {
            $goal->completed_at = $data['completed'] ? now() : null;
            if ($data['completed']) {
                $goal->progress = 100;
            }
            unset($data['completed']);
        }

        $goal->fill($data)->save();

        return back()->with('flash.success', 'Goal updated.');
    }

    public function destroy(Goal $goal): RedirectResponse
    {
        $this->authorize('delete', $goal);

        // Sub-goals would otherwise keep pointing at a soft-deleted parent and
        // disappear from the page, because the tree only roots a goal whose
        // parent is absent. Lifting them to where this goal sat keeps them.
        Goal::query()
            ->where('parent_id', $goal->id)
            ->update(['parent_id' => $goal->parent_id]);

        // A project milestone outlives the goal; it just stops rolling up.
        Milestone::query()
            ->where('goal_id', $goal->id)
            ->whereNotNull('project_id')
            ->update(['goal_id' => null]);

        // One that only ever existed for this goal has nowhere left to belong.
        Milestone::query()
            ->where('goal_id', $goal->id)
            ->whereNull('project_id')
            ->get()
            ->each(fn (Milestone $m) => $m->delete());

        $goal->delete();

        return back()->with('flash.success', 'Goal removed.');
    }

    /**
     * Goals belonging to the signed-in user in the current workspace. Goals are
     * personal, so this is the only set these endpoints may touch.
     */
    protected function ownedGoals(Request $request)
    {
        return Goal::query()
            ->forWorkspace($request->user()->currentWorkspace())
            ->where('user_id', $request->user()->id);
    }

    /**
     * Point each goal's `children` relation at the already-loaded siblings, so
     * computedProgress() can walk a tree of any depth without querying again.
     *
     * @param  Collection<int, Goal>  $goals
     */
    protected function linkChildren(Collection $goals): void
    {
        $byParent = $goals->groupBy('parent_id');

        foreach ($goals as $goal) {
            $goal->setRelation('children', $byParent->get($goal->id, collect())->values());
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function goalPayload(Goal $g): array
    {
        return [
            'id' => $g->id,
            'parent_id' => $g->parent_id,
            'type' => $g->type,
            'title' => $g->title,
            'description' => $g->description,
            'horizon' => $g->horizon,
            'status' => $g->status,
            'target_date' => $g->target_date?->toDateString(),
            'progress' => $g->computedProgress(),
            // The stored number, which only drives the bar on a goal with nothing
            // underneath it. The edit form needs it separately so it never writes
            // a rolled-up average back as though it were a hand-set value.
            'own_progress' => (int) $g->progress,
            'is_leaf' => $g->children->isEmpty()
                && $g->milestones->isEmpty()
                && $g->projects->isEmpty(),
            'overdue' => $g->isOverdue(),
            'completed_at' => $g->completed_at?->toIso8601String(),
            'url' => route('goals.show', $g->id),
            'milestones_count' => $g->milestones->count(),
            'projects_count' => $g->projects->count(),
            // The projects being run in service of this goal, each contributing
            // its own progress to the number above.
            'projects' => $g->projects->map(fn (Project $p) => [
                'id' => $p->id,
                'title' => $p->title,
                'progress' => $p->computedProgress(),
                'state' => $p->state,
                'due_date' => $p->due_date?->toDateString(),
                'url' => route('projects.show', $p->id),
            ])->values(),
            // Shown under the goal so progress is traceable to the work behind it
            // rather than being an unexplained percentage.
            'milestones' => $g->milestones->map(fn (Milestone $m) => [
                'id' => $m->id,
                'title' => $m->title,
                // True when this milestone sits inside a project that is itself
                // linked here: it still shows, but it is not counted twice.
                'counted_via_project' => $m->project_id !== null
                    && $g->projects->contains('id', $m->project_id),
                'description' => $m->description,
                'progress' => (int) $m->progress,
                'is_manual' => $m->tracksProgressManually(),
                'due_date' => $m->due_date?->toDateString(),
                'completed_at' => $m->completed_at?->toIso8601String(),
                'project' => $m->project ? [
                    'id' => $m->project->id,
                    'title' => $m->project->title,
                    'url' => route('projects.show', $m->project->id),
                ] : null,
            ])->values(),
        ];
    }

    /**
     * Headline counts for the page banner.
     *
     * @param  Collection<int, Goal>  $goals
     * @return array<string, int>
     */
    protected function stats(Collection $goals): array
    {
        $open = $goals->whereNull('completed_at');

        return [
            'total' => $goals->count(),
            'completed' => $goals->whereNotNull('completed_at')->count(),
            'at_risk' => $open->where('status', Goal::STATUS_AT_RISK)->count(),
            'off_track' => $open->where('status', Goal::STATUS_OFF_TRACK)->count(),
            'overdue' => $open->filter(fn (Goal $g) => $g->isOverdue())->count(),
        ];
    }

    /**
     * Projects a milestone may be attached to, for the inline milestone form.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function projectOptions(Request $request): array
    {
        return Project::query()
            ->forWorkspace($request->user()->currentWorkspace())
            ->orderBy('title')
            ->get(['id', 'title'])
            ->map(fn (Project $p) => ['id' => $p->id, 'title' => $p->title])
            ->all();
    }

    protected function nextPosition(Request $request, ?int $parentId): int
    {
        return (int) $this->ownedGoals($request)
            ->when(
                $parentId,
                fn ($q) => $q->where('parent_id', $parentId),
                fn ($q) => $q->whereNull('parent_id'),
            )
            ->max('position') + 1;
    }

    /**
     * A goal may only be parented to another goal the same person owns in the
     * same workspace.
     */
    protected function goalExistsRule(Request $request): Exists
    {
        return Rule::exists('goals', 'id')
            ->where('workspace_id', $request->user()->currentWorkspace()->id)
            ->where('user_id', $request->user()->id)
            ->whereNull('deleted_at');
    }
}
