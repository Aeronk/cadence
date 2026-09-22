<?php

namespace Tests\Feature\Goals;

use App\Models\Goal;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The parts of goal setup that had no way in through the UI: editing a goal at
 * all, saying how it is going, reparenting it, and attaching a milestone that
 * belongs to the goal rather than to some project invented to hold it.
 */
class GoalSetupTest extends TestCase
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

    public function test_a_goal_can_be_edited_through_the_update_endpoint(): void
    {
        $goal = $this->goal(['title' => 'Old title', 'status' => Goal::STATUS_ON_TRACK]);

        $this->actingAs($this->user)
            ->patch(route('goals.update', $goal), [
                'title' => 'Ship the platform',
                'description' => '<p>What done looks like</p>',
                'horizon' => 'quarter',
                'status' => Goal::STATUS_AT_RISK,
                'target_date' => '2026-12-31',
            ])
            ->assertRedirect();

        $fresh = $goal->fresh();
        $this->assertSame('Ship the platform', $fresh->title);
        $this->assertSame('quarter', $fresh->horizon);
        $this->assertSame(Goal::STATUS_AT_RISK, $fresh->status);
        $this->assertSame('2026-12-31', $fresh->target_date->toDateString());
    }

    public function test_an_unknown_status_is_rejected(): void
    {
        $goal = $this->goal();

        $this->actingAs($this->user)
            ->patch(route('goals.update', $goal), ['status' => 'vibes'])
            ->assertSessionHasErrors('status');
    }

    public function test_marking_a_goal_done_pins_it_at_full_progress(): void
    {
        $project = Project::factory()->for($this->workspace)->create(['created_by' => $this->user->id]);
        $goal = $this->goal();
        Milestone::factory()->for($project)->create(['goal_id' => $goal->id, 'progress' => 20]);

        $this->actingAs($this->user)
            ->patch(route('goals.update', $goal), ['completed' => true])
            ->assertRedirect();

        $fresh = $goal->fresh()->load(['children', 'milestones']);
        $this->assertNotNull($fresh->completed_at);
        // The tick and the bar have to agree; a completed goal reading 20% is a
        // contradiction on the page.
        $this->assertSame(100, $fresh->computedProgress());
    }

    public function test_a_goal_cannot_be_reparented_onto_its_own_descendant(): void
    {
        $vision = $this->goal(['type' => Goal::TYPE_VISION]);
        $child = $this->goal(['parent_id' => $vision->id]);
        $grandchild = $this->goal(['parent_id' => $child->id]);

        // Would detach the whole branch from every root, making it unreachable.
        $this->actingAs($this->user)
            ->patch(route('goals.update', $vision), ['parent_id' => $grandchild->id])
            ->assertSessionHasErrors('parent_id');

        $this->assertNull($vision->fresh()->parent_id);
    }

    public function test_a_goal_cannot_be_parented_to_someone_elses_goal(): void
    {
        $mine = $this->goal();
        $theirs = Goal::factory()->create();

        $this->actingAs($this->user)
            ->patch(route('goals.update', $mine), ['parent_id' => $theirs->id])
            ->assertSessionHasErrors('parent_id');
    }

    public function test_deleting_a_goal_lifts_its_children_rather_than_hiding_them(): void
    {
        $vision = $this->goal(['type' => Goal::TYPE_VISION]);
        $middle = $this->goal(['parent_id' => $vision->id]);
        $leaf = $this->goal(['parent_id' => $middle->id]);

        $this->actingAs($this->user)
            ->delete(route('goals.destroy', $middle))
            ->assertRedirect();

        // Left pointing at a soft-deleted parent, the leaf would vanish from the
        // tree entirely, since the page only roots goals whose parent is absent.
        $this->assertSame($vision->id, $leaf->fresh()->parent_id);
    }

    public function test_deleting_a_goal_unlinks_project_milestones_but_removes_its_own(): void
    {
        $project = Project::factory()->for($this->workspace)->create(['created_by' => $this->user->id]);
        $goal = $this->goal();

        $shared = Milestone::factory()->for($project)->create(['goal_id' => $goal->id]);
        $ownMilestone = Milestone::create([
            'workspace_id' => $this->workspace->id,
            'goal_id' => $goal->id,
            'title' => 'Personal checkpoint',
            'manual_progress' => 50,
        ]);

        $this->actingAs($this->user)
            ->delete(route('goals.destroy', $goal))
            ->assertRedirect();

        // The project's milestone is still that project's work.
        $this->assertNull($shared->fresh()->goal_id);
        $this->assertDatabaseHas('milestones', ['id' => $shared->id, 'deleted_at' => null]);

        // One that only ever existed for this goal has nowhere left to belong.
        $this->assertSoftDeleted('milestones', ['id' => $ownMilestone->id]);
    }

    public function test_a_milestone_can_belong_to_a_goal_with_no_project(): void
    {
        $goal = $this->goal();

        $this->actingAs($this->user)
            ->post(route('milestones.store'), [
                'goal_id' => $goal->id,
                'title' => 'Run 10km without stopping',
                'manual_progress' => 30,
            ])
            ->assertRedirect();

        $milestone = Milestone::firstWhere('title', 'Run 10km without stopping');
        $this->assertNull($milestone->project_id);
        $this->assertSame($goal->id, $milestone->goal_id);
        // Taken from the goal, since there is no project to read it from.
        $this->assertSame($this->workspace->id, $milestone->workspace_id);
        $this->assertSame(30, $milestone->progress);
    }

    public function test_a_milestone_with_neither_project_nor_goal_is_rejected(): void
    {
        $this->actingAs($this->user)
            ->post(route('milestones.store'), ['title' => 'Floating'])
            ->assertSessionHasErrors('project_id');
    }

    public function test_a_goal_only_milestone_cannot_be_unlinked_into_nothing(): void
    {
        $goal = $this->goal();
        $milestone = Milestone::create([
            'workspace_id' => $this->workspace->id,
            'goal_id' => $goal->id,
            'title' => 'Orphan risk',
        ]);

        // With no project to fall back on, unlinking would leave a row nobody
        // owns and no policy can reach.
        $this->actingAs($this->user)
            ->patch(route('milestones.update', $milestone), ['goal_id' => null])
            ->assertSessionHasErrors('goal_id');

        $this->assertSame($goal->id, $milestone->fresh()->goal_id);
    }

    public function test_goal_only_milestones_get_distinct_positions(): void
    {
        $goal = $this->goal();

        foreach (['First', 'Second', 'Third'] as $title) {
            Milestone::create([
                'workspace_id' => $this->workspace->id,
                'goal_id' => $goal->id,
                'title' => $title,
            ]);
        }

        // `where('project_id', null)` matches nothing in SQL, so the null branch
        // has to be asked as whereNull or every one lands at position 0.
        $positions = Milestone::where('goal_id', $goal->id)->pluck('position')->sort()->values()->all();
        $this->assertSame([0, 1, 2], $positions);
    }

    public function test_the_goal_detail_page_shows_the_goal_with_its_children_and_milestones(): void
    {
        $project = Project::factory()->for($this->workspace)->create(['created_by' => $this->user->id]);
        $goal = $this->goal(['title' => 'Grow the team']);
        $this->goal(['parent_id' => $goal->id, 'title' => 'Hire two engineers', 'progress' => 50]);
        Milestone::factory()->for($project)->create(['goal_id' => $goal->id, 'progress' => 100]);

        $this->actingAs($this->user)
            ->get(route('goals.show', $goal))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Goals/Show')
                ->where('goal.title', 'Grow the team')
                // (50 + 100) / 2
                ->where('goal.progress', 75)
                ->has('goal.milestones', 1)
                ->has('children', 1)
                ->has('projects'));
    }

    public function test_the_detail_page_never_offers_a_descendant_as_a_parent(): void
    {
        $goal = $this->goal(['title' => 'Root']);
        $child = $this->goal(['parent_id' => $goal->id, 'title' => 'Child']);
        $sibling = $this->goal(['title' => 'Elsewhere']);

        $this->actingAs($this->user)
            ->get(route('goals.show', $goal))
            ->assertOk()
            ->assertInertia(function (Assert $page) use ($sibling, $child, $goal) {
                $ids = collect($page->toArray()['props']['parent_options'])->pluck('id')->all();

                $this->assertContains($sibling->id, $ids);
                $this->assertNotContains($child->id, $ids);
                $this->assertNotContains($goal->id, $ids);
            });
    }

    public function test_another_user_cannot_open_a_goal(): void
    {
        $theirs = Goal::factory()->create();

        $this->actingAs($this->user)
            ->get(route('goals.show', $theirs))
            ->assertForbidden();
    }

    public function test_the_index_reports_how_many_goals_need_attention(): void
    {
        $this->goal(['status' => Goal::STATUS_AT_RISK]);
        $this->goal(['status' => Goal::STATUS_OFF_TRACK]);
        $this->goal(['status' => Goal::STATUS_ON_TRACK, 'target_date' => now()->subWeek()]);
        $this->goal(['completed_at' => now()]);
        // A finished goal past its date is not overdue; it is finished.
        $this->goal(['completed_at' => now(), 'target_date' => now()->subMonth()]);

        $this->actingAs($this->user)
            ->get(route('goals.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Goals/Index')
                ->where('stats.total', 5)
                ->where('stats.completed', 2)
                ->where('stats.at_risk', 1)
                ->where('stats.off_track', 1)
                ->where('stats.overdue', 1));
    }
}
