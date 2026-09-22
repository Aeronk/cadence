<?php

namespace Tests\Feature\Tasks;

use App\Models\Milestone;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskCategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_task_with_category(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentWorkspace())->create(['created_by' => $user->id]);

        $this->actingAs($user)->post(route('tasks.store'), [
            'project_id' => $project->id,
            'title' => 'Send report',
            'category' => 'work',
        ])->assertRedirect();

        $this->assertDatabaseHas('tasks', [
            'project_id' => $project->id,
            'title' => 'Send report',
            'category' => 'work',
        ]);
    }

    public function test_unknown_category_is_rejected(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentWorkspace())->create(['created_by' => $user->id]);

        $this->actingAs($user)->post(route('tasks.store'), [
            'project_id' => $project->id,
            'title' => 'X',
            'category' => 'wrong',
        ])->assertSessionHasErrors('category');
    }

    public function test_task_can_be_linked_to_milestone(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentWorkspace())->create(['created_by' => $user->id]);
        $milestone = Milestone::factory()->for($project)->create();

        $this->actingAs($user)->post(route('tasks.store'), [
            'project_id' => $project->id,
            'title' => 'Milestone-linked task',
            'milestone_id' => $milestone->id,
        ])->assertRedirect();

        $task = Task::firstWhere('title', 'Milestone-linked task');
        $this->assertSame($milestone->id, $task->milestone_id);
        $this->assertTrue($task->milestone->is($milestone));
    }

    public function test_category_can_be_changed_when_editing_a_task(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentWorkspace())->create(['created_by' => $user->id]);
        $task = Task::factory()->for($project)->create([
            'created_by' => $user->id,
            'category' => 'work',
        ]);

        $this->actingAs($user)
            ->patch(route('tasks.update', $task), ['category' => 'family'])
            ->assertRedirect();

        $this->assertSame('family', $task->fresh()->category);
    }

    public function test_category_can_be_cleared_from_the_edit_form(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentWorkspace())->create(['created_by' => $user->id]);
        $task = Task::factory()->for($project)->create([
            'created_by' => $user->id,
            'category' => 'work',
        ]);

        // A select posts an empty string for "None"; that has to mean null
        // rather than failing the `in` rule.
        $this->actingAs($user)
            ->patch(route('tasks.update', $task), ['category' => ''])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertNull($task->fresh()->category);
    }

    public function test_an_empty_repeat_selection_clears_the_rule(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentWorkspace())->create(['created_by' => $user->id]);
        $task = Task::factory()->for($project)->create([
            'created_by' => $user->id,
            'recurrence_rule' => 'weekly',
        ]);

        $this->actingAs($user)
            ->patch(route('tasks.update', $task), ['recurrence_rule' => ''])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertNull($task->fresh()->recurrence_rule);
    }

    public function test_filtering_tasks_by_category(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentWorkspace())->create(['created_by' => $user->id]);
        Task::factory()->for($project)->create(['created_by' => $user->id, 'category' => 'work']);
        Task::factory()->for($project)->create(['created_by' => $user->id, 'category' => 'family']);
        Task::factory()->for($project)->create(['created_by' => $user->id, 'category' => null]);

        $this->actingAs($user)
            ->get(route('tasks.index', ['category' => 'work']))
            ->assertInertia(fn ($p) => $p->component('Tasks/Index')->has('tasks', 1));
    }
}
