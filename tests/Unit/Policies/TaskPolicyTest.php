<?php

namespace Tests\Unit\Policies;

use App\Enums\WorkspaceRole;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskPolicyTest extends TestCase
{
    use RefreshDatabase;

    private function withRole(WorkspaceRole $role): array
    {
        $workspace = Workspace::factory()->create();
        $user = User::factory()->create();
        $workspace->members()->attach($user, ['role' => $role->value]);

        return [$workspace, $user];
    }

    public function test_outsider_cannot_view_task(): void
    {
        $task = Task::factory()->create();
        $outsider = User::factory()->create();

        $this->assertFalse($outsider->can('view', $task));
    }

    public function test_workspace_admin_can_view_update_delete_any_task(): void
    {
        [$workspace, $admin] = $this->withRole(WorkspaceRole::Admin);
        $project = Project::factory()->for($workspace)->create();
        $task = Task::factory()->for($project)->create();

        $this->assertTrue($admin->can('view', $task));
        $this->assertTrue($admin->can('update', $task));
        $this->assertTrue($admin->can('delete', $task));
    }

    public function test_assignee_can_view_and_update_but_not_delete(): void
    {
        [$workspace, $member] = $this->withRole(WorkspaceRole::Member);
        $project = Project::factory()->for($workspace)->create();
        $task = Task::factory()->for($project)->create();
        $task->assignees()->attach($member);

        $this->assertTrue($member->can('view', $task));
        $this->assertTrue($member->can('update', $task));
        $this->assertFalse($member->can('delete', $task));
    }

    public function test_creator_can_update_and_delete_own_task(): void
    {
        [$workspace, $member] = $this->withRole(WorkspaceRole::Member);
        $project = Project::factory()->for($workspace)->create();
        $task = Task::factory()->for($project)->create(['created_by' => $member->id]);

        $this->assertTrue($member->can('update', $task));
        $this->assertTrue($member->can('delete', $task));
    }

    public function test_workspace_member_unassigned_to_project_cannot_view(): void
    {
        [$workspace, $member] = $this->withRole(WorkspaceRole::Member);
        $project = Project::factory()->for($workspace)->create();
        $task = Task::factory()->for($project)->create();

        $this->assertFalse($member->can('view', $task));
    }
}
