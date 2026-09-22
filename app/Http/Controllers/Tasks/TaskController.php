<?php

namespace App\Http\Controllers\Tasks;

use App\Enums\Category;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\Task;
use App\Models\Trip;
use App\Models\User;
use App\Notifications\TaskAssigned;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Inertia\Response;

class TaskController extends Controller
{
    /**
     * Repeat rules a task understands. Kept beside the controller so the edit
     * dialog and Task::nextOccurrenceDate cannot drift apart.
     */
    public const RECURRENCE_OPTIONS = [
        ['value' => '', 'label' => 'Does not repeat'],
        ['value' => 'daily', 'label' => 'Daily'],
        ['value' => 'weekly', 'label' => 'Weekly'],
        ['value' => 'monthly', 'label' => 'Monthly'],
        ['value' => 'yearly', 'label' => 'Yearly'],
    ];

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Task::class);

        $user = $request->user();
        $workspace = $user->currentWorkspace();

        $query = Task::query()
            ->forWorkspace($workspace)
            ->with(['status', 'priority', 'assignees', 'tags', 'project:id,title']);

        if ($projectId = $request->integer('project_id')) {
            $project = Project::findOrFail($projectId);
            $this->authorize('view', $project);
            $query->where('project_id', $projectId);
        }

        if ($category = $request->string('category')->toString()) {
            $query->where('category', $category);
        }

        if (! $workspace->roleFor($user)?->canManageWorkspace()) {
            $query->where(function ($q) use ($user) {
                $q->where('created_by', $user->id)
                    ->orWhereHas('assignees', fn ($q) => $q->where('users.id', $user->id))
                    ->orWhereHas('project.members', fn ($q) => $q->where('users.id', $user->id));
            });
        }

        return Inertia::render('Tasks/Index', [
            'tasks' => $query->orderBy('position')->get(),
            'filters' => [
                'project_id' => $request->integer('project_id') ?: null,
                'category' => $request->string('category')->toString() ?: null,
            ],
            'categories' => Category::options(),
            'statuses' => $workspace->statuses()->orderBy('position')->get(['id', 'name', 'color']),
            'priorities' => $workspace->priorities()->orderBy('level')->get(['id', 'name', 'color', 'level']),
            'projects_for_select' => Project::query()
                ->forWorkspace($workspace)
                ->when(! $workspace->roleFor($user)?->canManageWorkspace(), function ($q) use ($user) {
                    $q->where(function ($q) use ($user) {
                        $q->where('created_by', $user->id)
                            ->orWhereHas('members', fn ($q) => $q->where('users.id', $user->id));
                    });
                })
                ->orderBy('title')
                ->get(['id', 'title']),
        ]);
    }

    public function show(Request $request, Task $task): Response
    {
        $this->authorize('view', $task);

        $workspace = $task->workspace;
        $user = $request->user();

        return Inertia::render('Tasks/Show', [
            'task' => $task->load([
                'status', 'priority', 'creator', 'assignees', 'tags', 'subtasks',
                'milestone', 'meeting.attendees:id,name,email', 'trip',
            ]),
            // Travel this task is tied to, plus the trips it could be tied to.
            // Scoped to the viewer because a trip is a personal record.
            'trip' => $task->trip ? [
                'id' => $task->trip->id,
                'name' => $task->trip->name,
                'destination' => trim(($task->trip->destination_city ?? '').' '.($task->trip->destination_country ?? '')) ?: null,
                'departs_at' => $task->trip->departs_at?->toDateString(),
                'returns_at' => $task->trip->returns_at?->toDateString(),
                'url' => route('trips.show', $task->trip->id),
            ] : null,
            'linkable_trips' => Trip::query()
                ->forWorkspace($workspace)
                ->where('user_id', $user->id)
                ->whereNot('status', Trip::STATUS_CANCELLED)
                ->orderByDesc('departs_at')
                ->limit(50)
                ->get(['id', 'name', 'destination_city', 'departs_at', 'returns_at'])
                ->map(fn (Trip $t) => [
                    'id' => $t->id,
                    'label' => $t->name.($t->destination_city ? ' — '.$t->destination_city : ''),
                    'departs_at' => $t->departs_at?->toDateString(),
                    'returns_at' => $t->returns_at?->toDateString(),
                    // Flagged so the picker can point out the obvious candidate
                    // rather than making someone match dates by eye.
                    'covers_due_date' => $task->due_date !== null
                        && $t->departs_at !== null
                        && $t->returns_at !== null
                        && $task->due_date->betweenIncluded($t->departs_at->startOfDay(), $t->returns_at->endOfDay()),
                ]),
            'day_context' => $this->dayContext($task, $user),
            'comments' => $task->comments()->with('user:id,name')->whereNull('parent_id')->latest()->get(),
            'milestones_for_select' => $task->project
                ? $task->project->milestones()->get(['id', 'title'])
                : [],
            'categories' => Category::options(),
            // The edit dialog needs the same option sets the index page has, plus
            // the people who can be assigned or invited.
            'statuses' => $workspace->statuses()->orderBy('position')->get(['id', 'name', 'color']),
            'priorities' => $workspace->priorities()->orderBy('level')->get(['id', 'name', 'color', 'level']),
            'assignable_users' => $workspace->members()
                ->orderBy('users.name')
                ->get(['users.id', 'users.name', 'users.email'])
                ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email]),
            'recurrence_options' => self::RECURRENCE_OPTIONS,
        ]);
    }

    public function store(StoreTaskRequest $request): RedirectResponse
    {
        $project = $request->project();
        $this->authorize('view', $project);
        $this->authorize('create', Task::class);

        $task = Task::create([
            'project_id' => $project->id,
            'workspace_id' => $project->workspace_id,
            'parent_id' => $request->input('parent_id'),
            'milestone_id' => $request->input('milestone_id'),
            'created_by' => $request->user()->id,
            'title' => $request->string('title'),
            'description' => $request->input('description'),
            'status_id' => $request->input('status_id'),
            'priority_id' => $request->input('priority_id'),
            'category' => $request->input('category'),
            'recurrence_rule' => $request->input('recurrence_rule'),
            'recurrence_ends_on' => $request->date('recurrence_ends_on'),
            'start_date' => $request->date('start_date'),
            'due_date' => $request->date('due_date'),
        ]);

        if ($assignees = (array) $request->input('assignee_ids', [])) {
            $task->assignees()->sync($assignees);
            $this->notifyAssignees($task, $assignees, $request->user());
        }

        if ($tagIds = (array) $request->input('tag_ids', [])) {
            $task->syncTagIds($tagIds);
        }

        return back()->with('flash.success', 'Task created.');
    }

    protected function notifyAssignees(Task $task, array $assigneeIds, User $assigner): void
    {
        $newAssignees = User::query()
            ->whereIn('id', $assigneeIds)
            ->whereKeyNot($assigner->id)
            ->get();

        if ($newAssignees->isNotEmpty()) {
            Notification::send($newAssignees, new TaskAssigned($task, $assigner));
        }
    }

    public function update(UpdateTaskRequest $request, Task $task): RedirectResponse
    {
        $this->authorize('update', $task);

        $task->fill($request->only([
            'title', 'description', 'status_id', 'priority_id', 'milestone_id',
            'trip_id', 'category', 'recurrence_rule', 'recurrence_ends_on',
            'start_date', 'due_date', 'position',
        ]));

        if ($request->has('completed')) {
            $task->completed_at = $request->boolean('completed') ? now() : null;
        }

        $task->save();

        if ($request->has('assignee_ids')) {
            $before = $task->assignees()->pluck('users.id')->all();
            $after = (array) $request->input('assignee_ids', []);
            $task->assignees()->sync($after);
            $newlyAssigned = array_diff($after, $before);
            if ($newlyAssigned) {
                $this->notifyAssignees($task, array_values($newlyAssigned), $request->user());
            }
        }

        if ($request->has('tag_ids')) {
            $task->syncTagIds((array) $request->input('tag_ids', []));
        }

        return back()->with('flash.success', 'Task updated.');
    }

    public function destroy(Task $task): RedirectResponse
    {
        $this->authorize('delete', $task);

        $task->delete();

        return back()->with('flash.success', 'Task deleted.');
    }

    /**
     * What else is happening on the day this task is due.
     *
     * Answers the question the task page could not: "can I actually do this
     * then?" A due date means little without the meetings and travel around it.
     *
     * @return array<string, mixed>
     */
    protected function dayContext(Task $task, $user): array
    {
        if ($task->due_date === null) {
            return ['date' => null, 'meetings' => [], 'trips' => []];
        }

        $dayStart = $task->due_date->copy()->startOfDay();
        $dayEnd = $task->due_date->copy()->endOfDay();
        $workspace = $task->workspace;

        $meetings = Meeting::query()
            ->forWorkspace($workspace)
            ->whereBetween('starts_at', [$dayStart, $dayEnd])
            ->where(fn ($q) => $q->where('host_id', $user->id)
                ->orWhereHas('attendees', fn ($q) => $q->where('users.id', $user->id)))
            // The meeting this task was scheduled as is already shown above it.
            ->when($task->meeting_id, fn ($q) => $q->whereKeyNot($task->meeting_id))
            ->orderBy('starts_at')
            ->get(['id', 'title', 'starts_at', 'ends_at', 'location'])
            ->map(fn (Meeting $m) => [
                'id' => $m->id,
                'title' => $m->title,
                'starts_at' => $m->starts_at?->toIso8601String(),
                'location' => $m->location,
                'url' => route('meetings.show', $m->id),
            ]);

        $trips = Trip::query()
            ->forWorkspace($workspace)
            ->where('user_id', $user->id)
            ->whereNot('status', Trip::STATUS_CANCELLED)
            ->intersectingRange($dayStart, $dayEnd)
            ->get(['id', 'name', 'destination_city', 'departs_at', 'returns_at'])
            ->map(fn (Trip $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'destination' => $t->destination_city,
                'url' => route('trips.show', $t->id),
            ]);

        return [
            'date' => $task->due_date->toDateString(),
            'meetings' => $meetings->values(),
            'trips' => $trips->values(),
        ];
    }
}
