<?php

namespace Tests\Unit\Models;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskTest extends TestCase
{
    use RefreshDatabase;

    public function test_task_belongs_to_project_workspace_creator(): void
    {
        $workspace = Workspace::factory()->create();
        $project = Project::factory()->for($workspace)->create();
        $user = User::factory()->create();
        $task = Task::factory()->for($project)->create(['created_by' => $user->id]);

        $this->assertTrue($task->project->is($project));
        $this->assertTrue($task->workspace->is($workspace));
        $this->assertTrue($task->creator->is($user));
    }

    public function test_workspace_id_is_inferred_from_project_when_missing(): void
    {
        $project = Project::factory()->create();
        $task = Task::factory()->for($project)->create(['workspace_id' => null]);

        $this->assertSame($project->workspace_id, $task->workspace_id);
    }

    public function test_position_auto_increments_within_project_and_parent_scope(): void
    {
        $project = Project::factory()->create();

        $t1 = Task::factory()->for($project)->create(['position' => null]);
        $t2 = Task::factory()->for($project)->create(['position' => null]);
        $t3 = Task::factory()->for($project)->create(['position' => null]);

        $this->assertSame(0, $t1->position);
        $this->assertSame(1, $t2->position);
        $this->assertSame(2, $t3->position);
    }

    public function test_subtask_relationship(): void
    {
        $project = Project::factory()->create();
        $parent = Task::factory()->for($project)->create();
        $child = Task::factory()->subtaskOf($parent)->create();

        $this->assertTrue($child->parent->is($parent));
        $this->assertTrue($parent->subtasks->contains($child));
    }

    public function test_mark_completed_and_incomplete(): void
    {
        $task = Task::factory()->create();
        $this->assertFalse($task->isCompleted());

        $task->markCompleted();
        $this->assertTrue($task->fresh()->isCompleted());

        $task->markIncomplete();
        $this->assertFalse($task->fresh()->isCompleted());
    }

    public function test_assignees(): void
    {
        $task = Task::factory()->create();
        $user = User::factory()->create();

        $task->assignees()->attach($user);

        $this->assertTrue($task->assignees->contains($user));
    }
}
