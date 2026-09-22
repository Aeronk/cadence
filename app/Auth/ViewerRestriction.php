<?php

namespace App\Auth;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Model;

/**
 * Enforces read-only access for workspace viewers.
 *
 * Registered as a `Gate::before` hook rather than threaded through each policy:
 * every existing policy grants writes on workspace membership alone, so a single
 * choke point is the only way to guarantee no ability is left unguarded. A
 * `before` callback that returns false short-circuits the whole check, which is
 * why this cannot be an `after` hook — those can only fill in a null result.
 */
class ViewerRestriction
{
    /**
     * Abilities that mutate workspace content. Anything not listed falls
     * through to the normal policy, so viewers keep full read access, can
     * still RSVP to a meeting they were invited to, and can leave a workspace.
     */
    public const WRITE_ABILITIES = [
        'create',
        'update',
        'delete',
        'restore',
        'forceDelete',
        'archive',
        'unarchive',
        'complete',
        'schedule',
        'unschedule',
        'manageMembers',
        'inviteMember',
        'removeMember',
    ];

    /**
     * Resolved roles for this request, keyed "userId:workspaceId". The hook runs
     * on every authorization check, so the role lookup must not hit the database
     * each time.
     *
     * @var array<string, WorkspaceRole|null>
     */
    protected array $roles = [];

    /**
     * @param  array<int, mixed>  $arguments
     * @return bool|null  false denies outright; null defers to the policy.
     */
    public function __invoke(User $user, string $ability, array $arguments = []): ?bool
    {
        if (! in_array($ability, self::WRITE_ABILITIES, true)) {
            return null;
        }

        $workspace = $this->resolveWorkspace($user, $arguments);

        if (! $workspace) {
            return null;
        }

        return $this->roleFor($user, $workspace) === WorkspaceRole::Viewer
            ? false
            : null;
    }

    /**
     * Prefer the workspace owning the model under test; fall back to the
     * workspace the user is currently acting in (the `create` case, where the
     * argument is a class name rather than an instance).
     *
     * @param  array<int, mixed>  $arguments
     */
    protected function resolveWorkspace(User $user, array $arguments): ?Workspace
    {
        $subject = $arguments[0] ?? null;

        if ($subject instanceof Workspace) {
            return $subject;
        }

        if ($subject instanceof Model) {
            $workspace = $subject->getAttribute('workspace');

            if ($workspace instanceof Workspace) {
                return $workspace;
            }

            if ($workspaceId = $subject->getAttribute('workspace_id')) {
                return Workspace::query()->find($workspaceId);
            }
        }

        return $user->currentWorkspace();
    }

    protected function roleFor(User $user, Workspace $workspace): ?WorkspaceRole
    {
        $key = $user->id.':'.$workspace->id;

        return $this->roles[$key] ??= $workspace->roleFor($user);
    }
}
