<?php

namespace Tests\Feature\Tasks;

use App\Models\Project;
use App\Models\Task;
use App\Models\Todo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskRecurrenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_completing_a_recurring_task_spawns_next_occurrence(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentWorkspace())->create(['created_by' => $user->id]);

        $task = Task::factory()->for($project)->create([
            'created_by' => $user->id,
            'title' => 'Weekly status report',
            'due_date' => now()->subDay(),
            'completed_at' => now(),
            'recurrence_rule' => 'weekly',
        ]);

        $this->artisan('recurring:generate')->assertExitCode(0);

        $children = Task::query()->where('recurrence_parent_id', $task->id)->get();
        $this->assertCount(1, $children);
        $this->assertSame(
            now()->subDay()->addWeek()->toDateString(),
            $children->first()->due_date->toDateString(),
        );
        $this->assertNull($children->first()->completed_at);
    }

    public function test_recurring_command_is_idempotent_across_runs(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentWorkspace())->create(['created_by' => $user->id]);

        Task::factory()->for($project)->create([
            'created_by' => $user->id,
            'due_date' => now()->subDay(),
            'completed_at' => now(),
            'recurrence_rule' => 'daily',
        ]);

        $this->artisan('recurring:generate');
        $this->artisan('recurring:generate');

        $this->assertSame(2, Task::count()); // original + one spawn, not three
    }

    public function test_recurrence_ends_on_date_stops_spawning(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentWorkspace())->create(['created_by' => $user->id]);

        Task::factory()->for($project)->create([
            'created_by' => $user->id,
            'due_date' => now(),
            'completed_at' => now(),
            'recurrence_rule' => 'weekly',
            'recurrence_ends_on' => now()->subDay(), // already expired
        ]);

        $this->artisan('recurring:generate');

        $this->assertSame(1, Task::count()); // only the parent
    }

    public function test_completed_recurring_todo_spawns_next(): void
    {
        $user = User::factory()->create();

        $todo = Todo::factory()->for($user)->for($user->currentWorkspace())->create([
            'due_date' => now()->subDay(),
            'completed_at' => now(),
            'recurrence_rule' => 'daily',
            'priority' => 'medium',
        ]);

        $this->artisan('recurring:generate');

        $children = Todo::query()->where('recurrence_parent_id', $todo->id)->get();
        $this->assertCount(1, $children);
    }
}
