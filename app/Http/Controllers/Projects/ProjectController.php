<?php

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Project::class);

        $user = $request->user();
        $workspace = $user->currentWorkspace();

        $projects = Project::query()
            ->forWorkspace($workspace)
            ->when(! $workspace->roleFor($user)?->canManageWorkspace(), function ($q) use ($user) {
                $q->where(function ($q) use ($user) {
                    $q->where('created_by', $user->id)
                        ->orWhereHas('members', fn ($q) => $q->where('users.id', $user->id));
                });
            })
            ->with(['status', 'priority', 'creator', 'tags'])
            ->orderByDesc('created_at')
            ->get();

        return Inertia::render('Projects/Index', [
            'projects' => $projects,
            'workspace' => $workspace,
        ]);
    }

    public function show(Project $project): Response
    {
        $this->authorize('view', $project);

        return Inertia::render('Projects/Show', [
            'project' => $project->load(['status', 'priority', 'creator', 'members', 'tags', 'clients']),
            'comments' => $project->comments()->with('user:id,name')->whereNull('parent_id')->latest()->get(),
            'tasks' => $project->tasks()
                ->with(['priority:id,name,color,level', 'assignees:id,name'])
                ->orderBy('position')
                ->get(['id', 'title', 'status_id', 'priority_id', 'due_date', 'completed_at', 'position']),
            'statuses' => $project->workspace->statuses()
                ->orderBy('position')
                ->get(['id', 'name', 'color', 'position', 'is_completed']),
        ]);
    }

    public function store(StoreProjectRequest $request): RedirectResponse
    {
        $this->authorize('create', Project::class);

        $user = $request->user();
        $workspace = $user->currentWorkspace();

        $project = Project::create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'title' => $request->string('title'),
            'description' => $request->input('description'),
            'status_id' => $request->input('status_id'),
            'priority_id' => $request->input('priority_id'),
            'start_date' => $request->date('start_date'),
            'due_date' => $request->date('due_date'),
        ]);

        $project->members()->syncWithoutDetaching(
            collect((array) $request->input('member_ids', []))
                ->mapWithKeys(fn ($id) => [(int) $id => ['role' => 'member']])
                ->all()
        );

        if (! $project->members()->where('users.id', $user->id)->exists()) {
            $project->members()->attach($user->id, ['role' => 'owner']);
        }

        if ($tagIds = $request->input('tag_ids')) {
            $project->syncTagIds((array) $tagIds);
        }

        if ($request->has('client_ids')) {
            $project->clients()->sync((array) $request->input('client_ids', []));
        }

        return to_route('projects.show', $project)->with('flash.success', 'Project created.');
    }

    public function update(UpdateProjectRequest $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $project->fill($request->only([
            'title', 'description', 'status_id', 'priority_id', 'start_date', 'due_date',
        ]))->save();

        if ($request->has('member_ids')) {
            $project->members()->sync(
                collect((array) $request->input('member_ids', []))
                    ->mapWithKeys(fn ($id) => [(int) $id => ['role' => 'member']])
                    ->all()
            );
        }

        if ($request->has('tag_ids')) {
            $project->syncTagIds((array) $request->input('tag_ids', []));
        }

        if ($request->has('client_ids')) {
            $project->clients()->sync((array) $request->input('client_ids', []));
        }

        return back()->with('flash.success', 'Project updated.');
    }

    public function destroy(Project $project): RedirectResponse
    {
        $this->authorize('delete', $project);

        $project->delete();

        return to_route('projects.index')->with('flash.success', 'Project deleted.');
    }
}
