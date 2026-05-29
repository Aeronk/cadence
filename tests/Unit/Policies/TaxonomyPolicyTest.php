<?php

namespace Tests\Unit\Policies;

use App\Enums\WorkspaceRole;
use App\Models\Priority;
use App\Models\Status;
use App\Models\Tag;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaxonomyPolicyTest extends TestCase
{
    use RefreshDatabase;

    private function setup_workspace_with_role(WorkspaceRole $role): array
    {
        $workspace = Workspace::factory()->create();
        $user = User::factory()->create();
        $workspace->members()->attach($user, ['role' => $role->value]);

        return [$workspace, $user];
    }

    public function test_outsider_cannot_view_status(): void
    {
        $workspace = Workspace::factory()->create();
        $outsider = User::factory()->create();
        $status = Status::factory()->for($workspace)->create();

        $this->assertFalse($outsider->can('view', $status));
    }

    public function test_member_can_view_but_not_update_status(): void
    {
        [$workspace, $member] = $this->setup_workspace_with_role(WorkspaceRole::Member);
        $status = Status::factory()->for($workspace)->create();

        $this->assertTrue($member->can('view', $status));
        $this->assertFalse($member->can('update', $status));
    }

    public function test_admin_can_update_status_priority_tag(): void
    {
        [$workspace, $admin] = $this->setup_workspace_with_role(WorkspaceRole::Admin);

        $status = Status::factory()->for($workspace)->create();
        $priority = Priority::factory()->for($workspace)->create();
        $tag = Tag::factory()->for($workspace)->create();

        $this->assertTrue($admin->can('update', $status));
        $this->assertTrue($admin->can('update', $priority));
        $this->assertTrue($admin->can('update', $tag));
    }

    public function test_default_status_and_priority_cannot_be_deleted(): void
    {
        [$workspace, $admin] = $this->setup_workspace_with_role(WorkspaceRole::Admin);

        $status = Status::factory()->for($workspace)->create(['is_default' => true]);
        $priority = Priority::factory()->for($workspace)->create(['is_default' => true]);

        $this->assertFalse($admin->can('delete', $status));
        $this->assertFalse($admin->can('delete', $priority));
    }
}
