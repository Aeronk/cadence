<?php

namespace Tests\Feature\Projects;

use App\Models\Meeting;
use App\Models\Note;
use App\Models\Project;
use App\Models\Task;
use App\Models\Todo;
use App\Models\Trip;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * A project is the outcome everything else hangs off. Travel, notes and to-dos
 * had no way to say which project they belonged to; now they do.
 */
class ProjectHubTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Workspace $workspace;

    protected Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->workspace = $this->user->currentWorkspace();
        $this->project = Project::factory()->for($this->workspace)->create(['created_by' => $this->user->id]);
    }

    public function test_a_trip_can_be_filed_under_a_project(): void
    {
        $this->actingAs($this->user)
            ->post(route('trips.store'), [
                'name' => 'Vendor visit',
                'project_id' => $this->project->id,
                'departs_at' => now()->addWeek()->toDateString(),
                'returns_at' => now()->addWeek()->addDays(3)->toDateString(),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('trips', [
            'name' => 'Vendor visit',
            'project_id' => $this->project->id,
        ]);
    }

    public function test_notes_and_todos_can_be_filed_under_a_project(): void
    {
        $this->actingAs($this->user)
            ->post(route('notes.store'), ['title' => 'Vendor pricing', 'project_id' => $this->project->id])
            ->assertRedirect();

        $this->actingAs($this->user)
            ->post(route('todos.store'), ['title' => 'Chase the quote', 'project_id' => $this->project->id])
            ->assertRedirect();

        $this->assertDatabaseHas('notes', ['title' => 'Vendor pricing', 'project_id' => $this->project->id]);
        $this->assertDatabaseHas('todos', ['title' => 'Chase the quote', 'project_id' => $this->project->id]);
    }

    public function test_a_project_from_another_workspace_is_rejected(): void
    {
        $foreign = Project::factory()->create();

        $this->actingAs($this->user)
            ->post(route('notes.store'), ['title' => 'sneaky', 'project_id' => $foreign->id])
            ->assertSessionHasErrors('project_id');
    }

    public function test_the_project_page_gathers_the_work_around_it(): void
    {
        Meeting::factory()->for($this->workspace)->create([
            'project_id' => $this->project->id,
            'host_id' => $this->user->id,
            'title' => 'Kickoff',
        ]);

        Trip::factory()->for($this->workspace)->create([
            'user_id' => $this->user->id,
            'project_id' => $this->project->id,
            'name' => 'Site inspection',
        ]);

        Note::factory()->create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'project_id' => $this->project->id,
        ]);

        Todo::factory()->create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'project_id' => $this->project->id,
        ]);

        $this->actingAs($this->user)
            ->get(route('projects.show', $this->project))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Projects/Show')
                ->has('meetings', 1)
                ->has('trips', 1)
                ->has('notes', 1)
                ->has('todos', 1)
                ->where('meetings.0.title', 'Kickoff')
                ->where('trips.0.name', 'Site inspection'));
    }

    public function test_the_project_page_does_not_leak_a_colleagues_notes_or_todos(): void
    {
        $colleague = User::factory()->create();
        $this->workspace->members()->attach($colleague->id, ['role' => 'member']);

        Note::factory()->create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $colleague->id,
            'project_id' => $this->project->id,
            'title' => 'Their private note',
        ]);

        Todo::factory()->create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $colleague->id,
            'project_id' => $this->project->id,
        ]);

        // Notes and to-dos are personal records; filing one under a shared
        // project must not publish it to the project team.
        $this->actingAs($this->user)
            ->get(route('projects.show', $this->project))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('notes', 0)
                ->has('todos', 0));
    }

    public function test_a_task_can_be_pinned_to_travel(): void
    {
        $trip = Trip::factory()->for($this->workspace)->create([
            'user_id' => $this->user->id,
            'name' => 'Nairobi',
        ]);

        $task = Task::factory()->for($this->project)->create(['created_by' => $this->user->id]);

        $this->actingAs($this->user)
            ->patch(route('tasks.update', $task), ['trip_id' => $trip->id])
            ->assertRedirect();

        $this->assertSame($trip->id, $task->fresh()->trip_id);

        // Sending null hands it back.
        $this->actingAs($this->user)
            ->patch(route('tasks.update', $task), ['trip_id' => null])
            ->assertRedirect();

        $this->assertNull($task->fresh()->trip_id);
    }

    public function test_a_task_cannot_be_pinned_to_someone_elses_travel(): void
    {
        $colleague = User::factory()->create();
        $this->workspace->members()->attach($colleague->id, ['role' => 'member']);

        $theirTrip = Trip::factory()->for($this->workspace)->create(['user_id' => $colleague->id]);
        $task = Task::factory()->for($this->project)->create(['created_by' => $this->user->id]);

        // Travel is a personal record, so you may only pin work to your own.
        $this->actingAs($this->user)
            ->patch(route('tasks.update', $task), ['trip_id' => $theirTrip->id])
            ->assertSessionHasErrors('trip_id');
    }

    public function test_the_task_page_shows_what_else_falls_on_its_due_date(): void
    {
        $dueDate = now()->addWeek()->startOfDay();

        $task = Task::factory()->for($this->project)->create([
            'created_by' => $this->user->id,
            'due_date' => $dueDate,
        ]);

        Meeting::factory()->for($this->workspace)->create([
            'host_id' => $this->user->id,
            'title' => 'Design review',
            'starts_at' => $dueDate->copy()->setTime(14, 0),
            'ends_at' => $dueDate->copy()->setTime(15, 0),
        ]);

        Trip::factory()->for($this->workspace)->create([
            'user_id' => $this->user->id,
            'name' => 'Nairobi',
            'departs_at' => $dueDate->copy()->subDay(),
            'returns_at' => $dueDate->copy()->addDay(),
        ]);

        // A due date means little on its own; this is what says whether the day
        // is actually free enough to do the work.
        $this->actingAs($this->user)
            ->get(route('tasks.show', $task))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Tasks/Show')
                ->where('day_context.date', $dueDate->toDateString())
                ->has('day_context.meetings', 1)
                ->where('day_context.meetings.0.title', 'Design review')
                ->has('day_context.trips', 1)
                // The overlapping trip is offered first as the obvious answer.
                ->where('linkable_trips.0.covers_due_date', true));
    }

    public function test_a_task_with_no_due_date_has_nothing_to_check_against(): void
    {
        $task = Task::factory()->for($this->project)->create([
            'created_by' => $this->user->id,
            'due_date' => null,
        ]);

        $this->actingAs($this->user)
            ->get(route('tasks.show', $task))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('day_context.date', null)
                ->has('day_context.meetings', 0));
    }
}
