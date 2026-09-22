<?php

namespace App\Http\Controllers\Workspaces;

use App\Enums\WorkspaceRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Services\Workspaces\InvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Throwable;

class WorkspaceInvitationController extends Controller
{
    public function __construct(protected InvitationService $invitations) {}

    public function store(Request $request, Workspace $workspace): RedirectResponse
    {
        $this->authorize('inviteMember', $workspace);

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', Rule::in(WorkspaceRole::invitableValues())],
        ]);

        $email = mb_strtolower(trim($data['email']));

        $existing = User::query()->where('email', $email)->first();

        if ($existing && $workspace->hasMember($existing)) {
            return back()->with('flash.error', 'That person is already a member of this workspace.');
        }

        try {
            $this->invitations->invite(
                $workspace,
                $email,
                WorkspaceRole::from($data['role']),
                $request->user(),
            );
        } catch (Throwable $e) {
            report($e);

            return back()->with('flash.error', 'Could not send the invitation: '.$e->getMessage());
        }

        return back()->with('flash.success', "Invitation sent to {$email}.");
    }

    public function resend(Request $request, Workspace $workspace, WorkspaceInvitation $invitation): RedirectResponse
    {
        $this->authorize('inviteMember', $workspace);
        abort_unless($invitation->workspace_id === $workspace->id, 404);

        try {
            $this->invitations->resend($invitation);
        } catch (RuntimeException $e) {
            return back()->with('flash.error', $e->getMessage());
        } catch (Throwable $e) {
            report($e);

            return back()->with('flash.error', 'Could not resend the invitation: '.$e->getMessage());
        }

        return back()->with('flash.success', "Invitation resent to {$invitation->email}.");
    }

    public function destroy(Request $request, Workspace $workspace, WorkspaceInvitation $invitation): RedirectResponse
    {
        $this->authorize('inviteMember', $workspace);
        abort_unless($invitation->workspace_id === $workspace->id, 404);

        $invitation->delete();

        return back()->with('flash.success', 'Invitation revoked.');
    }

    /**
     * Landing page for the emailed link. Public, because the invitee may not have
     * an account yet — the token is the credential.
     */
    public function show(Request $request, string $token): Response|RedirectResponse
    {
        $invitation = $this->invitations->findByToken($token);

        if (! $invitation) {
            return $this->render($request, [
                'state' => 'invalid',
                'message' => 'This invitation link is not valid. Ask whoever invited you to send a new one.',
            ]);
        }

        if ($invitation->isAccepted()) {
            return $this->render($request, [
                'state' => 'accepted',
                'workspace_name' => $invitation->workspace->name,
                'message' => 'This invitation has already been used.',
            ]);
        }

        if ($invitation->isExpired()) {
            return $this->render($request, [
                'state' => 'expired',
                'workspace_name' => $invitation->workspace->name,
                'message' => 'This invitation has expired. Ask for a new one to be sent.',
            ]);
        }

        $user = $request->user();

        if (! $user) {
            // Send them to register with the invited address pre-filled, and keep
            // the token so the invitation completes once they have an account.
            $request->session()->put('workspace_invitation_token', $token);

            return $this->render($request, [
                'state' => 'needs_account',
                'email' => $invitation->email,
                'workspace_name' => $invitation->workspace->name,
                'role' => $invitation->role->label(),
                'invited_by' => $invitation->invitedBy?->name,
                'register_url' => route('register', ['email' => $invitation->email]),
                'login_url' => route('login', ['email' => $invitation->email]),
            ]);
        }

        if (! hash_equals(mb_strtolower($user->email), mb_strtolower($invitation->email))) {
            return $this->render($request, [
                'state' => 'wrong_account',
                'email' => $invitation->email,
                'workspace_name' => $invitation->workspace->name,
                'message' => "This invitation was sent to {$invitation->email}, but you are signed in as {$user->email}.",
            ]);
        }

        $this->invitations->accept($invitation, $user);
        $request->session()->forget('workspace_invitation_token');
        $user->switchWorkspace($invitation->workspace);

        return redirect()->route('dashboard')
            ->with('flash.success', "You've joined {$invitation->workspace->name}.");
    }

    /**
     * @param  array<string, mixed>  $props
     */
    protected function render(Request $request, array $props): Response
    {
        return Inertia::render('Invitations/Show', $props);
    }
}
