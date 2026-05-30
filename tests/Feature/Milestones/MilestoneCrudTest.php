<?php

namespace Tests\Feature\Milestones;

use App\Models\Milestone;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MilestoneCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_add_a_milestone_to_their_project(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentWorkspace())->create(['created_by' => $user->id]);

        $this->actingAs($user)->post(route('milestones.store'), [
            'project_id' => $project->id,
            'title' => 'Beta launch',
            'due_date' => now()->addMonth()->toDateString(),
            'progress' => 25,
        ])->assertRedirect();

        $this->assertDatabaseHas('milestones', [
            'project_id' => $project->id,
            'workspace_id' => $project->workspace_id,
            'title' => 'Beta launch',
            'progress' => 25,
        ]);
    }

    public function test_outsider_cannot_add_milestone_to_foreign_project(): void
    {
        $user = User::factory()->create();
        $foreignProject = Project::factory()->create();

        $this->actingAs($user)->post(route('milestones.store'), [
            'project_id' => $foreignProject->id,
            'title' => 'sneaky',
        ])->assertSessionHasErrors('project_id');
    }

    public function test_milestone_can_be_marked_completed(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentWorkspace())->create(['created_by' => $user->id]);
        $milestone = Milestone::factory()->for($project)->create(['progress' => 30]);

        $this->actingAs($user)
            ->patch(route('milestones.update', $milestone), ['completed' => true])
            ->assertRedirect();

        $fresh = $milestone->fresh();
        $this->assertNotNull($fresh->completed_at);
        $this->assertSame(100, $fresh->progress);
    }

    public function test_creator_can_soft_delete_milestone(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentWorkspace())->create(['created_by' => $user->id]);
        $milestone = Milestone::factory()->for($project)->create();

        $this->actingAs($user)
            ->delete(route('milestones.destroy', $milestone))
            ->assertRedirect();

        $this->assertSoftDeleted('milestones', ['id' => $milestone->id]);
    }
}
