<?php

namespace App\Http\Controllers\Tasks;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskAssigned;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Inertia\Response;

class TaskController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Task::class);

        $user = $request->user();
        $workspace = $user->currentWorkspace();

        $query = Task::query()
            ->forWorkspace($workspace)
            ->with(['status', 'priority', 'assignees', 'tags']);

        if ($projectId = $request->integer('project_id')) {
            $project = Project::findOrFail($projectId);
            $this->authorize('view', $project);
            $query->where('project_id', $projectId);
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
            'filters' => ['project_id' => $request->integer('project_id') ?: null],
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

    public function show(Task $task): Response
    {
        $this->authorize('view', $task);

        return Inertia::render('Tasks/Show', [
            'task' => $task->load(['status', 'priority', 'creator', 'assignees', 'tags', 'subtasks']),
            'comments' => $task->comments()->with('user:id,name')->whereNull('parent_id')->latest()->get(),
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
            'created_by' => $request->user()->id,
            'title' => $request->string('title'),
            'description' => $request->input('description'),
            'status_id' => $request->input('status_id'),
            'priority_id' => $request->input('priority_id'),
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
            'title', 'description', 'status_id', 'priority_id', 'start_date', 'due_date', 'position',
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
}
