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
 * Projects are the work; goals are the outcome. Linking them is what turns
 * "we are busy" into "we are making progress against something".
 */
class GoalProjectLinkTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->workspace = $this->user->currentWorkspace();
    }

    protected function goal(array $attributes = []): Goal
    {
        return Goal::factory()->for($this->user)->for($this->workspace)->create($attributes);
    }

    protected function project(array $attributes = []): Project
    {
        return Project::factory()->for($this->workspace)->create($attributes + ['created_by' => $this->user->id]);
    }

    public function test_a_project_can_be_linked_to_a_goal_and_unlinked_again(): void
    {
        $goal = $this->goal();
        $project = $this->project(['title' => 'Platform rebuild']);

        $this->actingAs($this->user)
            ->post(route('goals.projects.store', $goal), ['project_id' => $project->id])
            ->assertRedirect();

        $this->assertTrue($goal->projects()->whereKey($project->id)->exists());

        $this->actingAs($this->user)
            ->delete(route('goals.projects.destroy', [$goal, $project]))
            ->assertRedirect();

        $this->assertFalse($goal->fresh()->projects()->whereKey($project->id)->exists());
        // Unlinking is not deleting.
        $this->assertDatabaseHas('projects', ['id' => $project->id, 'deleted_at' => null]);
    }

    public function test_one_project_can_serve_several_goals(): void
    {
        $launch = $this->goal(['title' => 'Launch v2']);
        $costs = $this->goal(['title' => 'Cut infrastructure cost']);
        $project = $this->project();

        foreach ([$launch, $costs] as $goal) {
            $this->actingAs($this->user)
                ->post(route('goals.projects.store', $goal), ['project_id' => $project->id])
                ->assertRedirect();
        }

        $this->assertSame(2, $project->goals()->count());
    }

    public function test_linking_the_same_project_twice_is_harmless(): void
    {
        $goal = $this->goal();
        $project = $this->project();

        foreach (range(1, 2) as $ignored) {
            $this->actingAs($this->user)
                ->post(route('goals.projects.store', $goal), ['project_id' => $project->id])
                ->assertRedirect()
                ->assertSessionHasNoErrors();
        }

        $this->assertSame(1, $goal->projects()->count());
    }

    public function test_a_project_from_another_workspace_cannot_be_linked(): void
    {
        $goal = $this->goal();
        $foreign = Project::factory()->create();

        $this->actingAs($this->user)
            ->post(route('goals.projects.store', $goal), ['project_id' => $foreign->id])
            ->assertSessionHasErrors('project_id');
    }

    public function test_another_user_cannot_link_projects_to_your_goal(): void
    {
        $goal = $this->goal();
        $project = $this->project();
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->post(route('goals.projects.store', $goal), ['project_id' => $project->id])
            ->assertForbidden();
    }

    public function test_a_linked_project_contributes_its_progress_to_the_goal(): void
    {
        $goal = $this->goal(['progress' => 0]);
        $project = $this->project();

        // Two milestones at 100 and 50 put the project at 75.
        Milestone::factory()->for($project)->create(['progress' => 100]);
        Milestone::factory()->for($project)->create(['progress' => 50]);

        // A milestone straight on the goal, with no project behind it.
        // `progress` is a cached column, so it has to be recomputed the way the
        // controller does — creating the model alone leaves it at zero.
        Milestone::create([
            'workspace_id' => $this->workspace->id,
            'goal_id' => $goal->id,
            'title' => 'Board sign-off',
            'manual_progress' => 65,
        ])->recomputeProgress();

        $goal->projects()->attach($project->id);

        // (75 + 65) / 2
        $this->assertSame(70, $goal->fresh()->load(['children', 'milestones', 'projects'])->computedProgress());
    }

    public function test_a_milestone_inside_a_linked_project_is_not_counted_twice(): void
    {
        $goal = $this->goal(['progress' => 0]);
        $project = $this->project();

        // The same milestone linked to BOTH the project and the goal directly.
        // Counting it at goal level as well as through the project would let one
        // piece of work vote twice and quietly inflate the number.
        Milestone::factory()->for($project)->create(['progress' => 40, 'goal_id' => $goal->id]);

        $goal->projects()->attach($project->id);

        $fresh = $goal->fresh()->load(['children', 'milestones', 'projects']);

        // The project is 40%, and that is the only vote: 40, not (40 + 40) / 2.
        $this->assertSame(40, $fresh->computedProgress());
    }

    public function test_a_project_with_no_milestones_falls_back_to_its_tasks(): void
    {
        $project = $this->project();

        Task::factory()->for($project)->create(['completed_at' => now(), 'created_by' => $this->user->id]);
        Task::factory()->for($project)->create(['completed_at' => now(), 'created_by' => $this->user->id]);
        Task::factory()->for($project)->create(['completed_at' => null, 'created_by' => $this->user->id]);
        Task::factory()->for($project)->create(['completed_at' => null, 'created_by' => $this->user->id]);

        $this->assertSame(50, $project->fresh()->computedProgress());
    }

    public function test_a_completed_project_reads_as_finished(): void
    {
        $project = $this->project(['state' => Project::STATE_COMPLETED]);
        Milestone::factory()->for($project)->create(['progress' => 10]);

        // The badge and the bar must not contradict each other.
        $this->assertSame(100, $project->fresh()->computedProgress());
    }

    public function test_the_goal_page_shows_the_projects_behind_the_number(): void
    {
        $goal = $this->goal();
        $project = $this->project(['title' => 'Platform rebuild']);
        $goal->projects()->attach($project->id);

        $this->actingAs($this->user)
            ->get(route('goals.show', $goal))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Goals/Show')
                ->has('goal.projects', 1)
                ->where('goal.projects.0.title', 'Platform rebuild')
                ->where('goal.projects_count', 1));
    }

    public function test_the_project_page_only_shows_your_own_goals(): void
    {
        $project = $this->project();
        $mine = $this->goal(['title' => 'My private goal']);

        $colleague = User::factory()->create();
        $this->workspace->members()->attach($colleague->id, ['role' => 'member']);
        $theirs = Goal::factory()->for($colleague)->for($this->workspace)->create(['title' => 'Their private goal']);

        $mine->projects()->attach($project->id);
        $theirs->projects()->attach($project->id);

        // A project page is shared with the team; a goal is not. Opening the
        // project must not expose a colleague's goals.
        $this->actingAs($this->user)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('goals', 1)
                ->where('goals.0.title', 'My private goal'));
    }
}
