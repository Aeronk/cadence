<?php

namespace Tests\Unit\Models;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_workspace_belongs_to_an_owner(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->for($owner, 'owner')->create();

        $this->assertTrue($workspace->owner->is($owner));
    }

    public function test_workspace_has_members_through_pivot_with_role(): void
    {
        $workspace = Workspace::factory()->create();
        $member = User::factory()->create();

        $workspace->members()->attach($member, ['role' => WorkspaceRole::Member->value]);

        $pivot = $workspace->members()->where('users.id', $member->id)->first()->pivot;
        $this->assertSame(WorkspaceRole::Member->value, $pivot->role);
    }

    public function test_owner_is_added_as_owner_member_when_workspace_is_created_via_factory(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->for($owner, 'owner')->create();

        $this->assertTrue(
            $workspace->members()->where('users.id', $owner->id)->exists(),
            'Owner should be a member of their workspace.'
        );
        $this->assertSame(
            WorkspaceRole::Owner->value,
            $workspace->members()->where('users.id', $owner->id)->first()->pivot->role
        );
    }

    public function test_role_for_returns_role_enum_for_member(): void
    {
        $workspace = Workspace::factory()->create();
        $user = User::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $this->assertSame(WorkspaceRole::Admin, $workspace->roleFor($user));
    }

    public function test_role_for_returns_null_for_non_member(): void
    {
        $workspace = Workspace::factory()->create();
        $outsider = User::factory()->create();

        $this->assertNull($workspace->roleFor($outsider));
    }

    public function test_slug_is_generated_from_name(): void
    {
        $workspace = Workspace::factory()->create(['name' => 'Acme Inc']);

        $this->assertSame('acme-inc', $workspace->slug);
    }

    public function test_slug_is_unique_across_workspaces(): void
    {
        Workspace::factory()->create(['name' => 'Acme Inc']);
        $second = Workspace::factory()->create(['name' => 'Acme Inc']);

        $this->assertNotSame('acme-inc', $second->slug);
        $this->assertStringStartsWith('acme-inc-', $second->slug);
    }
}
