<?php

namespace App\Http\Controllers\Notes;

use App\Http\Controllers\Concerns\OffersProjectOptions;
use App\Http\Controllers\Controller;
use App\Models\Note;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class NoteController extends Controller
{
    use OffersProjectOptions;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Note::class);

        $user = $request->user();
        $workspace = $user->currentWorkspace();

        $notes = Note::query()
            ->where('user_id', $user->id)
            ->when($workspace, fn ($q) => $q->forWorkspace($workspace))
            ->with('project:id,title')
            ->orderByDesc('is_pinned')
            ->orderByDesc('updated_at')
            ->get();

        return Inertia::render('Notes/Index', [
            'notes' => $notes,
            'projects' => $this->projectOptions($request),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Note::class);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
            'color' => ['nullable', 'string', 'max:32'],
            'is_pinned' => ['nullable', 'boolean'],
            // Filing this under a project is optional; it must be one in the
            // same workspace.
            'project_id' => [
                'nullable',
                Rule::exists('projects', 'id')
                    ->where('workspace_id', $request->user()->currentWorkspace()->id)
                    ->whereNull('deleted_at'),
            ],
        ]);

        Note::create($data + [
            'workspace_id' => $request->user()->currentWorkspace()->id,
            'user_id' => $request->user()->id,
        ]);

        return back()->with('flash.success', 'Note created.');
    }

    public function update(Request $request, Note $note): RedirectResponse
    {
        $this->authorize('update', $note);

        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
            'color' => ['nullable', 'string', 'max:32'],
            'is_pinned' => ['nullable', 'boolean'],
            // Filing this under a project is optional; it must be one in the
            // same workspace.
            'project_id' => [
                'nullable',
                Rule::exists('projects', 'id')
                    ->where('workspace_id', $request->user()->currentWorkspace()->id)
                    ->whereNull('deleted_at'),
            ],
        ]);

        $note->fill($data)->save();

        return back()->with('flash.success', 'Note updated.');
    }

    public function destroy(Note $note): RedirectResponse
    {
        $this->authorize('delete', $note);
        $note->delete();

        return back()->with('flash.success', 'Note deleted.');
    }
}
