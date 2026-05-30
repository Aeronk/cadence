<?php

namespace App\Http\Controllers\Workspaces;

use App\Enums\WorkspaceRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceController extends Controller
{
    public function edit(Request $request): Response
    {
        $workspace = $request->user()->currentWorkspace();
        abort_unless($workspace !== null, 404);
        $this->authorize('view', $workspace);

        $members = $workspace->members()
            ->select('users.id', 'users.name', 'users.email')
            ->orderBy('users.name')
            ->get()
            ->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->pivot->role,
                'is_owner' => $u->id === $workspace->owner_id,
            ]);

        return Inertia::render('settings/Workspace', [
            'workspace' => [
                'id' => $workspace->id,
                'name' => $workspace->name,
                'slug' => $workspace->slug,
                'description' => $workspace->description,
                'is_personal' => $workspace->is_personal,
                'owner_id' => $workspace->owner_id,
            ],
            'members' => $members,
            'viewer_role' => $workspace->roleFor($request->user())?->value,
            'roles' => collect(WorkspaceRole::cases())->map(fn ($r) => $r->value),
        ]);
    }

    public function update(Request $request, Workspace $workspace): RedirectResponse
    {
        $this->authorize('update', $workspace);

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $workspace->fill($data)->save();

        return back()->with('flash.success', 'Workspace updated.');
    }

    public function destroy(Request $request, Workspace $workspace): RedirectResponse
    {
        $this->authorize('delete', $workspace);

        $workspace->delete();

        return redirect()->route('dashboard')->with('flash.success', 'Workspace deleted.');
    }

    public function inviteMember(Request $request, Workspace $workspace): RedirectResponse
    {
        $this->authorize('inviteMember', $workspace);

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', Rule::in([WorkspaceRole::Admin->value, WorkspaceRole::Member->value])],
        ]);

        $user = User::firstOrCreate(
            ['email' => $data['email']],
            [
                'name' => Str::before($data['email'], '@'),
                'password' => Hash::make(Str::random(40)),
            ],
        );

        if ($workspace->hasMember($user)) {
            return back()->with('flash.error', 'That user is already a member.');
        }

        $workspace->members()->attach($user->id, [
            'role' => $data['role'],
            'joined_at' => now(),
        ]);

        return back()->with('flash.success', 'Member invited.');
    }

    public function updateMemberRole(Request $request, Workspace $workspace, User $member): RedirectResponse
    {
        $this->authorize('inviteMember', $workspace);

        if ($member->id === $workspace->owner_id) {
            return back()->with('flash.error', 'Cannot change the workspace owner role.');
        }

        $data = $request->validate([
            'role' => ['required', Rule::in([WorkspaceRole::Admin->value, WorkspaceRole::Member->value])],
        ]);

        $workspace->members()->updateExistingPivot($member->id, ['role' => $data['role']]);

        return back()->with('flash.success', 'Role updated.');
    }

    public function removeMember(Request $request, Workspace $workspace, User $member): RedirectResponse
    {
        $this->authorize('removeMember', $workspace);

        if ($member->id === $workspace->owner_id) {
            return back()->with('flash.error', 'Cannot remove the workspace owner.');
        }

        $workspace->members()->detach($member->id);

        return back()->with('flash.success', 'Member removed.');
    }

    public function leave(Request $request, Workspace $workspace): RedirectResponse
    {
        $this->authorize('leave', $workspace);

        $workspace->members()->detach($request->user()->id);

        return redirect()->route('dashboard')->with('flash.success', 'Left the workspace.');
    }
}
