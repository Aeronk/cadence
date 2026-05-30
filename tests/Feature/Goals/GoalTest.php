<?php

namespace Tests\Feature\Goals;

use App\Models\Goal;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoalTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_a_goal_hierarchy(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('goals.store'), [
            'type' => 'vision',
            'title' => 'Become a country director by 2030',
            'horizon' => 'year',
            'target_date' => '2030-12-31',
        ])->assertRedirect();

        $vision = Goal::firstWhere('type', 'vision');

        $this->actingAs($user)->post(route('goals.store'), [
            'type' => 'goal',
            'parent_id' => $vision->id,
            'title' => 'Lead a $1M program in 2026',
            'horizon' => 'year',
            'target_date' => '2026-12-31',
        ])->assertRedirect();

        $this->assertSame(2, Goal::count());
        $this->assertSame($vision->id, Goal::firstWhere('type', 'goal')->parent_id);
    }

    public function test_goal_progress_rolls_up_from_children_and_milestones(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentWorkspace())->create(['created_by' => $user->id]);

        $parent = Goal::factory()->for($user)->for($user->currentWorkspace())->create(['progress' => 0]);
        Goal::factory()->for($user)->for($user->currentWorkspace())->create([
            'parent_id' => $parent->id,
            'progress' => 60,
        ]);
        Milestone::factory()->for($project)->create([
            'goal_id' => $parent->id,
            'progress' => 40,
        ]);

        // (60 + 40) / 2 = 50
        $this->assertSame(50, $parent->fresh()->load(['children', 'milestones'])->computedProgress());
    }

    public function test_user_cannot_see_another_users_goals(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $g = Goal::factory()->for($alice)->for($alice->currentWorkspace())->create();

        $this->actingAs($bob)
            ->patch(route('goals.update', $g), ['title' => 'pwned'])
            ->assertForbidden();
    }
}
