<?php

namespace Tests\Feature\Activity;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_project_records_activity(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('projects.store'), ['title' => 'New initiative'])
            ->assertRedirect();

        $this->assertDatabaseHas('activity_logs', [
            'workspace_id' => $user->currentWorkspace()->id,
            'actor_id' => $user->id,
            'action' => 'created',
            'subject_type' => Project::class,
        ]);
    }

    public function test_creating_a_task_records_activity(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentWorkspace())->create(['created_by' => $user->id]);

        $this->actingAs($user)
            ->post(route('tasks.store'), ['project_id' => $project->id, 'title' => 'Spec'])
            ->assertRedirect();

        $this->assertDatabaseHas('activity_logs', [
            'workspace_id' => $user->currentWorkspace()->id,
            'actor_id' => $user->id,
            'action' => 'created',
            'subject_type' => Task::class,
        ]);
    }

    public function test_user_can_view_activity_feed(): void
    {
        $user = User::factory()->create();
        Project::factory()->for($user->currentWorkspace())->create(['created_by' => $user->id]);

        $this->actingAs($user)
            ->get(route('activity.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Activity/Index')
                ->has('activity')
            );
    }
}
