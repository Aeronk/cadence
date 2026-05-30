<?php

namespace Tests\Feature\Workspaces;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspaceSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_rename_workspace(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id, 'name' => 'Old']);

        $this->actingAs($owner)
            ->patch(route('workspace.update', $workspace), ['name' => 'New'])
            ->assertRedirect();

        $this->assertSame('New', $workspace->fresh()->name);
    }

    public function test_member_cannot_rename_workspace(): void
    {
        $workspace = Workspace::factory()->create();
        $member = User::factory()->create();
        $workspace->members()->attach($member, ['role' => WorkspaceRole::Member->value]);

        $this->actingAs($member)
            ->patch(route('workspace.update', $workspace), ['name' => 'pwned'])
            ->assertForbidden();
    }

    public function test_admin_can_invite_a_new_user_by_email(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $admin = User::factory()->create();
        $workspace->members()->attach($admin, ['role' => WorkspaceRole::Admin->value]);

        $this->actingAs($admin)
            ->post(route('workspace.members.invite', $workspace), [
                'email' => 'newhire@example.com',
                'role' => 'member',
            ])
            ->assertRedirect();

        $invited = User::firstWhere('email', 'newhire@example.com');
        $this->assertNotNull($invited);
        $this->assertTrue($workspace->hasMember($invited));
        $this->assertSame(WorkspaceRole::Member, $workspace->roleFor($invited));
    }

    public function test_inviting_existing_member_returns_error_flash(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $existing = User::factory()->create(['email' => 'existing@example.com']);
        $workspace->members()->attach($existing, ['role' => WorkspaceRole::Member->value]);

        $this->actingAs($owner)
            ->from(route('workspace.edit'))
            ->post(route('workspace.members.invite', $workspace), [
                'email' => 'existing@example.com',
                'role' => 'member',
            ])
            ->assertSessionHas('flash.error');
    }

    public function test_admin_can_change_member_role(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $member = User::factory()->create();
        $workspace->members()->attach($member, ['role' => WorkspaceRole::Member->value]);

        $this->actingAs($owner)
            ->patch(route('workspace.members.update', [$workspace, $member]), ['role' => 'admin'])
            ->assertRedirect();

        $this->assertSame(WorkspaceRole::Admin, $workspace->fresh()->roleFor($member));
    }

    public function test_owner_role_cannot_be_changed(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($owner)
            ->from(route('workspace.edit'))
            ->patch(route('workspace.members.update', [$workspace, $owner]), ['role' => 'member'])
            ->assertSessionHas('flash.error');

        $this->assertSame(WorkspaceRole::Owner, $workspace->roleFor($owner));
    }

    public function test_admin_can_remove_member(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $member = User::factory()->create();
        $workspace->members()->attach($member, ['role' => WorkspaceRole::Member->value]);

        $this->actingAs($owner)
            ->delete(route('workspace.members.remove', [$workspace, $member]))
            ->assertRedirect();

        $this->assertFalse($workspace->fresh()->hasMember($member));
    }

    public function test_owner_cannot_be_removed(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($owner)
            ->from(route('workspace.edit'))
            ->delete(route('workspace.members.remove', [$workspace, $owner]))
            ->assertSessionHas('flash.error');

        $this->assertTrue($workspace->fresh()->hasMember($owner));
    }

    public function test_owner_can_delete_non_personal_workspace(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id, 'is_personal' => false]);

        $this->actingAs($owner)
            ->delete(route('workspace.destroy', $workspace))
            ->assertRedirect(route('dashboard'));

        $this->assertSoftDeleted('workspaces', ['id' => $workspace->id]);
    }

    public function test_personal_workspace_cannot_be_deleted(): void
    {
        $user = User::factory()->create();
        $personal = $user->workspaces()->first();
        $this->assertTrue($personal->is_personal);

        $this->actingAs($user)
            ->delete(route('workspace.destroy', $personal))
            ->assertForbidden();
    }

    public function test_member_can_leave_non_personal_workspace(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $member = User::factory()->create();
        $workspace->members()->attach($member, ['role' => WorkspaceRole::Member->value]);

        $this->actingAs($member)
            ->post(route('workspace.leave', $workspace))
            ->assertRedirect(route('dashboard'));

        $this->assertFalse($workspace->fresh()->hasMember($member));
    }

    public function test_owner_cannot_leave_their_own_workspace(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($owner)
            ->post(route('workspace.leave', $workspace))
            ->assertForbidden();
    }
}
