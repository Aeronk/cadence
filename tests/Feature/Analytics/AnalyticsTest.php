<?php

namespace Tests\Feature\Analytics;

use App\Enums\WorkspaceRole;
use App\Models\Project;
use App\Models\Task;
use App\Models\Todo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_analytics_page_returns_expected_buckets(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentWorkspace())->create(['created_by' => $user->id]);

        $task = Task::factory()->for($project)->create([
            'created_by' => $user->id,
            'category' => 'work',
            'completed_at' => now(),
        ]);
        $task->assignees()->attach($user);

        Todo::factory()->for($user)->for($user->currentWorkspace())->create([
            'category' => 'personal',
            'completed_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('analytics.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Analytics/Index')
                ->has('task_status')
                ->where('productivity.tasks_completed_30d', 1)
                ->where('productivity.todos_completed_30d', 1)
                ->has('life_balance', 8)
            );
    }

    public function test_workload_only_visible_to_admins(): void
    {
        $admin = User::factory()->create();
        $admin->currentWorkspace()->members()->updateExistingPivot(
            $admin->id,
            ['role' => WorkspaceRole::Owner->value],
        );
        $member = User::factory()->create();
        $admin->currentWorkspace()->members()->attach($member, ['role' => WorkspaceRole::Member->value]);

        $this->actingAs($admin)
            ->get(route('analytics.index'))
            ->assertInertia(fn (Assert $p) => $p->has('workload', 2));

        $this->actingAs($member)
            ->get(route('analytics.index'))
            ->assertInertia(fn (Assert $p) => $p->has('workload', 0));
    }
}
