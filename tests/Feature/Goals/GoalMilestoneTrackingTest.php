<?php

namespace Tests\Feature\Goals;

use App\Models\Goal;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Goal progress is the average of its milestones, and a milestone's progress is
 * the share of its tasks that are done. The link that makes this work — the
 * milestone's goal_id — existed in the schema but was unreachable through the
 * model and the controller, so rollup always read zero.
 */
class GoalMilestoneTrackingTest extends TestCase
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

    protected function goal(array $attributes = []): Goal
    {
        return Goal::factory()->create($attributes + [
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
        ]);
    }

    protected function milestone(array $attributes = []): Milestone
    {
        return Milestone::factory()->for($this->project)->create($attributes);
    }

    protected function taskFor(Milestone $milestone, bool $completed = false): Task
    {
        return Task::factory()->for($this->project)->create([
            'milestone_id' => $milestone->id,
            'created_by' => $this->user->id,
            'completed_at' => $completed ? now() : null,
        ]);
    }

    public function test_a_milestone_can_be_attached_to_a_goal(): void
    {
        $goal = $this->goal();
        $milestone = $this->milestone();

        $this->actingAs($this->user)
            ->patch(route('milestones.update', $milestone), ['goal_id' => $goal->id])
            ->assertRedirect();

        $this->assertSame($goal->id, $milestone->fresh()->goal_id);
        $this->assertSame($goal->id, $milestone->fresh()->goal->id);
    }

    public function test_a_milestone_cannot_be_attached_to_someone_elses_goal(): void
    {
        $stranger = User::factory()->create();
        $foreignGoal = Goal::factory()->create([
            'workspace_id' => $stranger->currentWorkspace()->id,
            'user_id' => $stranger->id,
        ]);

        $milestone = $this->milestone();

        $this->actingAs($this->user)
            ->patch(route('milestones.update', $milestone), ['goal_id' => $foreignGoal->id])
            ->assertSessionHasErrors('goal_id');

        $this->assertNull($milestone->fresh()->goal_id);
    }

    public function test_milestone_progress_is_counted_from_its_tasks(): void
    {
        $milestone = $this->milestone();

        $this->taskFor($milestone, completed: true);
        $this->taskFor($milestone, completed: true);
        $this->taskFor($milestone);
        $this->taskFor($milestone);

        $this->assertSame(50, $milestone->fresh()->progress);
        $this->assertFalse($milestone->fresh()->tracksProgressManually());
    }

    public function test_completing_a_task_moves_the_milestone_bar(): void
    {
        $milestone = $this->milestone();
        $task = $this->taskFor($milestone);
        $this->taskFor($milestone);

        $this->assertSame(0, $milestone->fresh()->progress);

        $this->actingAs($this->user)
            ->patch(route('tasks.update', $task), ['completed' => true])
            ->assertRedirect();

        $this->assertSame(50, $milestone->fresh()->progress);
    }

    public function test_moving_a_task_updates_both_milestones(): void
    {
        $from = $this->milestone();
        $to = $this->milestone();

        $task = $this->taskFor($from, completed: true);

        $this->assertSame(100, $from->fresh()->progress);
        $this->assertSame(0, $to->fresh()->progress);

        $this->actingAs($this->user)
            ->patch(route('tasks.update', $task), ['milestone_id' => $to->id])
            ->assertRedirect();

        $this->assertSame(0, $from->fresh()->progress);
        $this->assertSame(100, $to->fresh()->progress);
    }

    public function test_a_milestone_with_no_tasks_sits_at_zero(): void
    {
        $milestone = $this->milestone();

        $this->assertSame(0, $milestone->fresh()->recomputeProgress());
    }

    public function test_a_manual_figure_overrides_the_task_count(): void
    {
        $milestone = $this->milestone();
        $this->taskFor($milestone, completed: true);
        $this->taskFor($milestone);

        $this->assertSame(50, $milestone->fresh()->progress);

        $this->actingAs($this->user)
            ->patch(route('milestones.update', $milestone), ['manual_progress' => 80])
            ->assertRedirect();

        $fresh = $milestone->fresh();
        $this->assertSame(80, $fresh->progress);
        $this->assertTrue($fresh->tracksProgressManually());
    }

    public function test_clearing_the_manual_figure_hands_progress_back_to_the_tasks(): void
    {
        $milestone = $this->milestone(['manual_progress' => 80, 'progress' => 80]);
        $this->taskFor($milestone, completed: true);
        $this->taskFor($milestone);
        $this->taskFor($milestone);
        $this->taskFor($milestone);

        $this->actingAs($this->user)
            ->patch(route('milestones.update', $milestone), ['manual_progress' => null])
            ->assertRedirect();

        $fresh = $milestone->fresh();
        $this->assertSame(25, $fresh->progress);
        $this->assertFalse($fresh->tracksProgressManually());
    }

    public function test_goal_progress_averages_its_milestones(): void
    {
        $goal = $this->goal(['progress' => 0]);

        $full = $this->milestone(['goal_id' => $goal->id]);
        $this->taskFor($full, completed: true);

        $half = $this->milestone(['goal_id' => $goal->id]);
        $this->taskFor($half, completed: true);
        $this->taskFor($half);

        // (100 + 50) / 2
        $this->assertSame(75, $goal->fresh()->computedProgress());
    }

    public function test_goal_progress_rolls_up_through_child_goals(): void
    {
        $vision = $this->goal(['type' => 'vision', 'progress' => 0]);
        $objective = $this->goal(['type' => 'objective', 'parent_id' => $vision->id, 'progress' => 0]);

        $milestone = $this->milestone(['goal_id' => $objective->id]);
        $this->taskFor($milestone, completed: true);
        $this->taskFor($milestone);

        $this->assertSame(50, $objective->fresh()->computedProgress());
        $this->assertSame(50, $vision->fresh()->computedProgress());
    }

    public function test_the_goals_page_lists_each_goals_milestones(): void
    {
        $goal = $this->goal(['title' => 'Ship v2']);
        $milestone = $this->milestone(['goal_id' => $goal->id, 'title' => 'Beta']);
        $this->taskFor($milestone, completed: true);

        // Unattached milestones are offered for linking.
        $this->milestone(['title' => 'Orphan']);

        $this->actingAs($this->user)
            ->get(route('goals.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Goals/Index')
                ->where('goals.0.title', 'Ship v2')
                ->where('goals.0.milestones_count', 1)
                ->where('goals.0.progress', 100)
                ->where('goals.0.milestones.0.title', 'Beta')
                ->where('goals.0.milestones.0.progress', 100)
                ->has('linkable_milestones', 1)
                ->where('linkable_milestones.0.title', 'Orphan')
            );
    }

    public function test_marking_a_milestone_complete_pins_it_at_one_hundred(): void
    {
        $milestone = $this->milestone();
        $this->taskFor($milestone);
        $this->taskFor($milestone);

        $this->actingAs($this->user)
            ->patch(route('milestones.update', $milestone), ['completed' => true])
            ->assertRedirect();

        $fresh = $milestone->fresh();
        $this->assertNotNull($fresh->completed_at);
        $this->assertSame(100, $fresh->progress);
    }
}
