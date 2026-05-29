<?php

namespace Tests\Feature\Tasks;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TaskCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_a_task_in_their_project(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentWorkspace())->create(['created_by' => $user->id]);

        $this->actingAs($user)
            ->post(route('tasks.store'), [
                'project_id' => $project->id,
                'title' => 'Write spec',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('tasks', [
            'project_id' => $project->id,
            'workspace_id' => $project->workspace_id,
            'title' => 'Write spec',
            'created_by' => $user->id,
        ]);
    }

    public function test_user_cannot_create_task_in_project_outside_their_workspace(): void
    {
        $user = User::factory()->create();
        $foreignProject = Project::factory()->create();

        $this->actingAs($user)
            ->from(route('tasks.index'))
            ->post(route('tasks.store'), [
                'project_id' => $foreignProject->id,
                'title' => 'Sneaky',
            ])
            ->assertSessionHasErrors('project_id');
    }

    public function test_assignee_can_mark_task_complete(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentWorkspace())->create(['created_by' => $user->id]);
        $task = Task::factory()->for($project)->create();
        $task->assignees()->attach($user);

        $this->actingAs($user)
            ->patch(route('tasks.update', $task), ['completed' => true])
            ->assertRedirect();

        $this->assertNotNull($task->fresh()->completed_at);
    }

    public function test_listing_tasks_filters_by_project(): void
    {
        $user = User::factory()->create();
        $workspace = $user->currentWorkspace();
        $projectA = Project::factory()->for($workspace)->create(['created_by' => $user->id]);
        $projectB = Project::factory()->for($workspace)->create(['created_by' => $user->id]);

        Task::factory()->for($projectA)->count(3)->create(['created_by' => $user->id]);
        Task::factory()->for($projectB)->count(2)->create(['created_by' => $user->id]);

        $this->actingAs($user)
            ->get(route('tasks.index', ['project_id' => $projectA->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Tasks/Index')
                ->has('tasks', 3)
            );
    }

    public function test_creator_can_soft_delete_task(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentWorkspace())->create(['created_by' => $user->id]);
        $task = Task::factory()->for($project)->create(['created_by' => $user->id]);

        $this->actingAs($user)
            ->delete(route('tasks.destroy', $task))
            ->assertRedirect();

        $this->assertSoftDeleted('tasks', ['id' => $task->id]);
    }
}
