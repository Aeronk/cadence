<?php

namespace Tests\Unit\Policies;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspacePolicyTest extends TestCase
{
    use RefreshDatabase;

    private function workspaceWithMember(WorkspaceRole $role): array
    {
        $workspace = Workspace::factory()->create();
        $user = User::factory()->create();
        $workspace->members()->attach($user, ['role' => $role->value]);

        return [$workspace, $user];
    }

    public function test_outsider_cannot_view_workspace(): void
    {
        $workspace = Workspace::factory()->create();
        $outsider = User::factory()->create();

        $this->assertFalse($outsider->can('view', $workspace));
    }

    public function test_member_can_view_workspace(): void
    {
        [$workspace, $member] = $this->workspaceWithMember(WorkspaceRole::Member);

        $this->assertTrue($member->can('view', $workspace));
    }

    public function test_only_owner_can_delete_workspace(): void
    {
        [$workspace, $admin] = $this->workspaceWithMember(WorkspaceRole::Admin);

        $this->assertFalse($admin->can('delete', $workspace));
        $this->assertTrue($workspace->owner->can('delete', $workspace));
    }

    public function test_owner_and_admin_can_update_workspace(): void
    {
        [$workspace, $admin] = $this->workspaceWithMember(WorkspaceRole::Admin);
        [$workspace2, $member] = $this->workspaceWithMember(WorkspaceRole::Member);

        $this->assertTrue($admin->can('update', $workspace));
        $this->assertTrue($workspace->owner->can('update', $workspace));
        $this->assertFalse($member->can('update', $workspace2));
    }

    public function test_owner_and_admin_can_invite_members(): void
    {
        [$workspace, $admin] = $this->workspaceWithMember(WorkspaceRole::Admin);
        [$workspace2, $member] = $this->workspaceWithMember(WorkspaceRole::Member);

        $this->assertTrue($admin->can('inviteMember', $workspace));
        $this->assertFalse($member->can('inviteMember', $workspace2));
    }
}
