<?php

namespace App\Http\Controllers\Comments;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CommentController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'commentable_type' => ['required', Rule::in(['task', 'project'])],
            'commentable_id' => ['required', 'integer'],
            'parent_id' => ['nullable', 'exists:comments,id'],
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $commentable = $this->resolveCommentable($data['commentable_type'], $data['commentable_id']);
        $this->authorize('create', [Comment::class, $commentable]);

        $commentable->comments()->create([
            'user_id' => $request->user()->id,
            'parent_id' => $data['parent_id'] ?? null,
            'body' => $data['body'],
        ]);

        return back()->with('flash.success', 'Comment posted.');
    }

    public function update(Request $request, Comment $comment): RedirectResponse
    {
        $this->authorize('update', $comment);

        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);
        $comment->update($data);

        return back()->with('flash.success', 'Comment updated.');
    }

    public function destroy(Comment $comment): RedirectResponse
    {
        $this->authorize('delete', $comment);
        $comment->delete();

        return back()->with('flash.success', 'Comment deleted.');
    }

    protected function resolveCommentable(string $type, int $id)
    {
        return match ($type) {
            'task' => Task::findOrFail($id),
            'project' => Project::findOrFail($id),
        };
    }
}
