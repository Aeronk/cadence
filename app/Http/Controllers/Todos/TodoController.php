<?php

namespace App\Http\Controllers\Todos;

use App\Http\Controllers\Controller;
use App\Models\Todo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TodoController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Todo::class);

        $user = $request->user();
        $workspace = $user->currentWorkspace();

        $todos = Todo::query()
            ->where('user_id', $user->id)
            ->when($workspace, fn ($q) => $q->forWorkspace($workspace))
            ->orderBy('completed_at')
            ->orderBy('position')
            ->orderByDesc('created_at')
            ->get();

        return Inertia::render('Todos/Index', [
            'todos' => $todos,
            'categories' => \App\Enums\Category::options(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Todo::class);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'priority' => ['nullable', Rule::in(['low', 'medium', 'high'])],
            'category' => ['nullable', Rule::in(\App\Enums\Category::values())],
            'due_date' => ['nullable', 'date'],
        ]);

        Todo::create($data + [
            'workspace_id' => $request->user()->currentWorkspace()->id,
            'user_id' => $request->user()->id,
            'priority' => $data['priority'] ?? 'medium',
        ]);

        return back()->with('flash.success', 'Todo added.');
    }

    public function update(Request $request, Todo $todo): RedirectResponse
    {
        $this->authorize('update', $todo);

        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'priority' => ['nullable', Rule::in(['low', 'medium', 'high'])],
            'category' => ['nullable', Rule::in(\App\Enums\Category::values())],
            'due_date' => ['nullable', 'date'],
            'completed' => ['nullable', 'boolean'],
            'position' => ['nullable', 'integer', 'min:0'],
        ]);

        if (array_key_exists('completed', $data)) {
            $todo->completed_at = $data['completed'] ? now() : null;
            unset($data['completed']);
        }

        $todo->fill($data)->save();

        return back()->with('flash.success', 'Todo updated.');
    }

    public function destroy(Todo $todo): RedirectResponse
    {
        $this->authorize('delete', $todo);
        $todo->delete();

        return back()->with('flash.success', 'Todo deleted.');
    }
}
