<?php

namespace App\Policies;

use App\Models\Comment;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class CommentPolicy
{
    public function view(User $user, Comment $comment): bool
    {
        return $this->canViewCommentable($user, $comment->commentable);
    }

    public function create(User $user, Model $commentable): bool
    {
        return $this->canViewCommentable($user, $commentable);
    }

    public function update(User $user, Comment $comment): bool
    {
        return $comment->user_id === $user->id;
    }

    public function delete(User $user, Comment $comment): bool
    {
        if ($comment->user_id === $user->id) {
            return true;
        }

        $commentable = $comment->commentable;
        $workspace = $commentable->workspace ?? null;

        return $workspace?->roleFor($user)?->canManageWorkspace() ?? false;
    }

    protected function canViewCommentable(User $user, Model $commentable): bool
    {
        if ($commentable instanceof Task) {
            return $user->can('view', $commentable);
        }

        if ($commentable instanceof Project) {
            return $user->can('view', $commentable);
        }

        return false;
    }
}
