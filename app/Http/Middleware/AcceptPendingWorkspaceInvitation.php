<?php

namespace App\Http\Middleware;

use App\Services\Workspaces\InvitationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Completes an invitation that was opened before the invitee had an account.
 *
 * The invitation page stashes the token in the session and sends them to
 * register or sign in; this picks it up on their first authenticated request so
 * they land in the workspace without having to re-open the email.
 */
class AcceptPendingWorkspaceInvitation
{
    public const SESSION_KEY = 'workspace_invitation_token';

    public function __construct(protected InvitationService $invitations) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $token = $request->session()->get(self::SESSION_KEY);

        if (! $user || ! is_string($token) || $token === '') {
            return $next($request);
        }

        // One attempt either way: a token that does not apply to this account
        // should not follow them around the app.
        $request->session()->forget(self::SESSION_KEY);

        $invitation = $this->invitations->findByToken($token);

        if (! $invitation || ! $invitation->isPending()) {
            return $next($request);
        }

        if (! hash_equals(mb_strtolower($user->email), mb_strtolower($invitation->email))) {
            return $next($request);
        }

        $this->invitations->accept($invitation, $user);
        $user->switchWorkspace($invitation->workspace);

        $request->session()->flash(
            'flash.success',
            "You've joined {$invitation->workspace->name}.",
        );

        return $next($request);
    }
}
