<?php

namespace Tests\Unit\Policies;

use App\Enums\WorkspaceRole;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectPolicyTest extends TestCase
{
    use RefreshDatabase;

    private function workspaceWith(WorkspaceRole $role): array
    {
        $workspace = Workspace::factory()->create();
        $user = User::factory()->create();
        $workspace->members()->attach($user, ['role' => $role->value]);

        return [$workspace, $user];
    }

    public function test_outsider_cannot_view_project(): void
    {
        $project = Project::factory()->create();
        $outsider = User::factory()->create();

        $this->assertFalse($outsider->can('view', $project));
    }

    public function test_workspace_admin_can_view_any_project(): void
    {
        [$workspace, $admin] = $this->workspaceWith(WorkspaceRole::Admin);
        $project = Project::factory()->for($workspace)->create();

        $this->assertTrue($admin->can('view', $project));
        $this->assertTrue($admin->can('update', $project));
        $this->assertTrue($admin->can('delete', $project));
    }

    public function test_member_can_only_view_projects_they_belong_to(): void
    {
        [$workspace, $member] = $this->workspaceWith(WorkspaceRole::Member);

        $assigned = Project::factory()->for($workspace)->create();
        $assigned->members()->attach($member, ['role' => 'member']);
        $other = Project::factory()->for($workspace)->create();

        $this->assertTrue($member->can('view', $assigned));
        $this->assertFalse($member->can('view', $other));
    }

    public function test_creator_can_update_and_delete_own_project(): void
    {
        [$workspace, $member] = $this->workspaceWith(WorkspaceRole::Member);
        $project = Project::factory()->for($workspace)->create(['created_by' => $member->id]);

        $this->assertTrue($member->can('update', $project));
        $this->assertTrue($member->can('delete', $project));
    }

    public function test_member_cannot_update_project_they_did_not_create(): void
    {
        [$workspace, $member] = $this->workspaceWith(WorkspaceRole::Member);
        $project = Project::factory()->for($workspace)->create();
        $project->members()->attach($member, ['role' => 'member']);

        $this->assertFalse($member->can('update', $project));
    }
}
