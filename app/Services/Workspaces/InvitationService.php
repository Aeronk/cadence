<?php

namespace App\Services\Workspaces;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Notifications\WorkspaceInvited;
use Illuminate\Support\Facades\Notification;
use RuntimeException;

class InvitationService
{
    /**
     * Create (or refresh) an invitation for an address and mail the link.
     *
     * Re-inviting an address that already has a pending invitation rotates its
     * token and re-sends rather than stacking rows, so a workspace only ever has
     * one live link per address.
     */
    public function invite(
        Workspace $workspace,
        string $email,
        WorkspaceRole $role,
        ?User $invitedBy = null,
    ): WorkspaceInvitation {
        $email = mb_strtolower(trim($email));

        $invitation = $workspace->invitations()
            ->where('email', $email)
            ->whereNull('accepted_at')
            ->first();

        if (! $invitation) {
            $invitation = new WorkspaceInvitation([
                'workspace_id' => $workspace->id,
                'email' => $email,
            ]);
            $invitation->workspace_id = $workspace->id;
        }

        $invitation->fill([
            'email' => $email,
            'role' => $role->value,
            'invited_by_id' => $invitedBy?->id,
            'expires_at' => now()->addDays(WorkspaceInvitation::TTL_DAYS),
        ]);

        $invitation->rotateToken();
        $invitation->save();

        $this->send($invitation, isResend: false);

        return $invitation;
    }

    /**
     * Re-send an existing invitation with a fresh token, which also invalidates
     * whatever link is sitting in the old email.
     */
    public function resend(WorkspaceInvitation $invitation): WorkspaceInvitation
    {
        if ($invitation->isAccepted()) {
            throw new RuntimeException('That invitation has already been accepted.');
        }

        if (! $invitation->canBeResent()) {
            throw new RuntimeException(sprintf(
                'Please wait %d more seconds before resending.',
                $invitation->secondsUntilResendAllowed(),
            ));
        }

        $invitation->rotateToken();
        // Resending restarts the clock, so a long-expired invitation becomes
        // usable again without the admin having to delete and recreate it.
        $invitation->expires_at = now()->addDays(WorkspaceInvitation::TTL_DAYS);
        $invitation->save();

        $this->send($invitation, isResend: true);

        return $invitation;
    }

    /**
     * Attach the invitee and close the invitation. The caller is responsible for
     * having checked that the invitation is valid and that the address matches.
     */
    public function accept(WorkspaceInvitation $invitation, User $user): void
    {
        $workspace = $invitation->workspace;

        if (! $workspace->hasMember($user)) {
            $workspace->members()->attach($user->id, [
                'role' => $invitation->role->value,
                'joined_at' => now(),
            ]);
        }

        $invitation->forceFill([
            'accepted_at' => now(),
            'accepted_by_id' => $user->id,
        ])->save();
    }

    /**
     * Resolve a plaintext token to its invitation. Returns null for unknown
     * tokens; expiry and acceptance are left for the caller to report on.
     */
    public function findByToken(string $token): ?WorkspaceInvitation
    {
        return WorkspaceInvitation::query()
            ->with(['workspace', 'invitedBy'])
            ->where('token_hash', WorkspaceInvitation::hashToken($token))
            ->first();
    }

    /**
     * Mail the invitation. Routed to the raw address rather than a user, because
     * the invitee may not have an account yet; when they do, the notification
     * goes to the user so it also lands in their in-app list.
     */
    protected function send(WorkspaceInvitation $invitation, bool $isResend): void
    {
        $invitation->loadMissing(['workspace', 'invitedBy']);

        $token = $invitation->plainToken();

        if (! $token) {
            throw new RuntimeException('Cannot send an invitation without a freshly minted token.');
        }

        $acceptUrl = route('workspace.invitations.accept', ['token' => $token]);
        $notification = new WorkspaceInvited($invitation, $acceptUrl, $isResend);

        $existingUser = User::query()->where('email', $invitation->email)->first();

        if ($existingUser) {
            $existingUser->notify($notification);
        } else {
            Notification::route('mail', $invitation->email)->notify($notification);
        }

        $invitation->markSent();
    }
}
