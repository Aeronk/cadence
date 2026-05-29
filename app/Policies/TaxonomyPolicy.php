<?php

namespace App\Policies;

use App\Models\Priority;
use App\Models\Status;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class TaxonomyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->currentWorkspace() !== null;
    }

    public function view(User $user, Model $taxonomy): bool
    {
        return $taxonomy->workspace->hasMember($user);
    }

    public function create(User $user): bool
    {
        $workspace = $user->currentWorkspace();

        return $workspace !== null
            && ($workspace->roleFor($user)?->canManageWorkspace() ?? false);
    }

    public function update(User $user, Model $taxonomy): bool
    {
        return $taxonomy->workspace->roleFor($user)?->canManageWorkspace() ?? false;
    }

    public function delete(User $user, Model $taxonomy): bool
    {
        if ($taxonomy instanceof Status && $taxonomy->is_default) {
            return false;
        }
        if ($taxonomy instanceof Priority && $taxonomy->is_default) {
            return false;
        }

        return $taxonomy->workspace->roleFor($user)?->canManageWorkspace() ?? false;
    }
}
