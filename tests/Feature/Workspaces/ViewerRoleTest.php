<?php

namespace Tests\Feature\Workspaces;

use App\Enums\WorkspaceRole;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Viewers are read-only. Every policy in the app grants writes on workspace
 * membership alone, so the guarantee comes from a single Gate::before hook —
 * these tests are what stop that hook regressing silently.
 */
class ViewerRoleTest extends TestCase
{
    use RefreshDatabase;

    protected Workspace $workspace;

    protected User $viewer;

    protected User $member;

    protected Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $owner = User::factory()->create();
        $this->workspace = Workspace::factory()->create(['owner_id' => $owner->id]);

        $this->viewer = User::factory()->create();
        $this->workspace->members()->attach($this->viewer, ['role' => WorkspaceRole::Viewer->value]);

        $this->member = User::factory()->create();
        $this->workspace->members()->attach($this->member, ['role' => WorkspaceRole::Member->value]);

        $this->project = Project::factory()->for($this->workspace)->create(['created_by' => $owner->id]);
        $this->project->members()->attach([$this->viewer->id, $this->member->id]);
    }

    /** Acting as someone in the shared workspace rather than their personal one. */
    protected function actingInWorkspace(User $user): self
    {
        $this->actingAs($user);
        $user->switchWorkspace($this->workspace);

        return $this;
    }

    public function test_the_role_reports_itself_as_read_only(): void
    {
        $this->assertFalse(WorkspaceRole::Viewer->canEdit());
        $this->assertFalse(WorkspaceRole::Viewer->canManageWorkspace());

        $this->assertTrue(WorkspaceRole::Member->canEdit());
        $this->assertTrue(WorkspaceRole::Admin->canEdit());
        $this->assertTrue(WorkspaceRole::Owner->canEdit());
    }

    public function test_a_viewer_can_read_a_project(): void
    {
        $this->actingInWorkspace($this->viewer)
            ->get(route('projects.show', $this->project))
            ->assertOk();
    }

    public function test_a_viewer_cannot_create_a_task(): void
    {
        $this->actingInWorkspace($this->viewer)
            ->post(route('tasks.store'), [
                'project_id' => $this->project->id,
                'title' => 'Sneaky task',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('tasks', ['title' => 'Sneaky task']);
    }

    public function test_a_viewer_cannot_update_a_task(): void
    {
        $task = Task::factory()->for($this->project)->create(['title' => 'Untouched']);

        $this->actingInWorkspace($this->viewer)
            ->patch(route('tasks.update', $task), ['title' => 'Edited'])
            ->assertForbidden();

        $this->assertSame('Untouched', $task->fresh()->title);
    }

    public function test_a_viewer_cannot_complete_a_task(): void
    {
        $task = Task::factory()->for($this->project)->create();

        $this->actingInWorkspace($this->viewer)
            ->patch(route('tasks.update', $task), ['completed' => true])
            ->assertForbidden();

        $this->assertNull($task->fresh()->completed_at);
    }

    public function test_a_viewer_cannot_delete_a_task(): void
    {
        $task = Task::factory()->for($this->project)->create();

        $this->actingInWorkspace($this->viewer)
            ->delete(route('tasks.destroy', $task))
            ->assertForbidden();

        $this->assertNotSoftDeleted('tasks', ['id' => $task->id]);
    }

    public function test_a_viewer_cannot_create_a_project(): void
    {
        $this->actingInWorkspace($this->viewer)
            ->post(route('projects.store'), ['title' => 'Viewer project'])
            ->assertForbidden();
    }

    public function test_a_viewer_cannot_create_a_milestone(): void
    {
        $this->actingInWorkspace($this->viewer)
            ->post(route('milestones.store'), [
                'project_id' => $this->project->id,
                'title' => 'Viewer milestone',
            ])
            ->assertForbidden();
    }

    public function test_a_viewer_cannot_schedule_a_task(): void
    {
        $task = Task::factory()->for($this->project)->create();

        $this->actingInWorkspace($this->viewer)
            ->post(route('tasks.schedule.store', $task), [
                'starts_at' => now()->addDay()->toDateTimeString(),
                'ends_at' => now()->addDay()->addHour()->toDateTimeString(),
                'meeting_type' => 'online',
            ])
            ->assertForbidden();

        $this->assertNull($task->fresh()->meeting_id);
    }

    public function test_a_viewer_cannot_invite_anyone(): void
    {
        $this->actingInWorkspace($this->viewer)
            ->post(route('workspace.invitations.store', $this->workspace), [
                'email' => 'nope@example.com',
                'role' => 'member',
            ])
            ->assertForbidden();
    }

    public function test_a_member_in_the_same_workspace_can_still_write(): void
    {
        // Guards against the hook denying more than it should.
        $task = Task::factory()->for($this->project)->create(['title' => 'Before']);

        $this->actingInWorkspace($this->member)
            ->patch(route('tasks.update', $task), ['title' => 'After'])
            ->assertRedirect();

        $this->assertSame('After', $task->fresh()->title);
    }

    public function test_a_viewer_can_still_leave_the_workspace(): void
    {
        $this->actingInWorkspace($this->viewer)
            ->post(route('workspace.leave', $this->workspace))
            ->assertRedirect();

        $this->assertFalse($this->workspace->fresh()->hasMember($this->viewer));
    }
}
