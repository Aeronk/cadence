<?php

namespace App\Models;

use App\Enums\WorkspaceRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    public const SESSION_WORKSPACE_KEY = 'current_workspace_id';

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'onboarded_at' => 'datetime',
            'notification_preferences' => 'array',
        ];
    }

    public const NOTIFICATION_KINDS = [
        'meeting_invited' => 'Meeting invitations',
        'meeting_reminder' => 'Meeting reminders',
        'task_assigned' => 'Task assignments',
        'workspace_invited' => 'Workspace invitations',
        'reminder' => 'Scheduled reminders',
        'test' => 'Test notifications',
    ];

    public const NOTIFICATION_CHANNELS = ['database', 'mail', 'broadcast'];

    /**
     * Returns true if the user wants this notification kind on this channel.
     * Defaults to ON when the user has not changed their preferences.
     */
    public function wantsNotification(string $kind, string $channel): bool
    {
        $prefs = $this->notification_preferences ?? [];
        if (! isset($prefs[$kind][$channel])) {
            return true;
        }

        return (bool) $prefs[$kind][$channel];
    }

    protected static function booted(): void
    {
        static::created(function (User $user): void {
            $name = trim((string) $user->name) !== '' ? $user->name."'s Workspace" : 'Personal Workspace';

            Workspace::create([
                'owner_id' => $user->id,
                'name' => $name,
                'is_personal' => true,
            ]);
        });
    }

    public function ownedWorkspaces(): HasMany
    {
        return $this->hasMany(Workspace::class, 'owner_id');
    }

    public function workspaces(): BelongsToMany
    {
        return $this->belongsToMany(Workspace::class, 'workspace_user')
            ->withPivot('role', 'joined_at')
            ->withTimestamps();
    }

    public function currentWorkspace(): ?Workspace
    {
        if (session()->isStarted() && ($sessionId = session(self::SESSION_WORKSPACE_KEY))) {
            $workspace = $this->workspaces()->where('workspaces.id', $sessionId)->first();
            if ($workspace) {
                return $workspace;
            }
        }

        return $this->workspaces()->orderBy('workspaces.created_at')->first();
    }

    public function switchWorkspace(Workspace $workspace): bool
    {
        if (! $workspace->hasMember($this)) {
            return false;
        }

        session([self::SESSION_WORKSPACE_KEY => $workspace->id]);

        return true;
    }

    public function roleIn(Workspace $workspace): ?WorkspaceRole
    {
        return $workspace->roleFor($this);
    }
}
