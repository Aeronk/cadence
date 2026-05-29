<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\User;

class ClientPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->currentWorkspace() !== null;
    }

    public function view(User $user, Client $client): bool
    {
        return $client->workspace->hasMember($user);
    }

    public function create(User $user): bool
    {
        return $user->currentWorkspace()?->hasMember($user) ?? false;
    }

    public function update(User $user, Client $client): bool
    {
        return $client->workspace->roleFor($user)?->canManageWorkspace()
            || $client->created_by === $user->id;
    }

    public function delete(User $user, Client $client): bool
    {
        return $this->update($user, $client);
    }
}
