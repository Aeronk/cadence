<?php

namespace Tests\Feature\AI;

use App\Models\Meeting;
use App\Models\Task;
use App\Models\User;
use App\Services\AI\BriefingComposer;
use App\Services\AI\FakeProvider;
use App\Services\AI\Provider as AIProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BriefingTest extends TestCase
{
    use RefreshDatabase;

    public function test_briefing_index_renders_with_no_briefing(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('briefing.index'))
            ->assertInertia(fn (Assert $p) => $p
                ->component('Briefing/Index')
                ->where('briefing', null)
            );
    }

    public function test_regenerate_composes_briefing_with_today_payload(): void
    {
        $user = User::factory()->create();
        $ws = $user->currentWorkspace();
        $project = \App\Models\Project::factory()->for($ws)->create(['created_by' => $user->id]);

        $task = Task::factory()->for($project)->create([
            'created_by' => $user->id,
            'due_date' => now()->toDateString(),
            'title' => 'Pay venue deposit',
        ]);
        $task->assignees()->attach($user);

        Meeting::factory()->for($ws)->create([
            'host_id' => $user->id,
            'starts_at' => now()->setTime(9, 0),
            'ends_at' => now()->setTime(10, 0),
        ]);

        /** @var FakeProvider $fake */
        $fake = app(AIProvider::class);
        $fake->queue("Focus on the venue deposit; finish before the 9am sync.");

        $this->actingAs($user)
            ->post(route('briefing.regenerate'))
            ->assertRedirect();

        $this->assertDatabaseHas('daily_briefings', [
            'user_id' => $user->id,
            'workspace_id' => $ws->id,
            'briefing_date' => now()->toDateString(),
        ]);

        $b = \App\Models\DailyBriefing::firstWhere('user_id', $user->id);
        $this->assertCount(1, $b->payload['tasks_due_today']);
        $this->assertCount(1, $b->payload['meetings_today']);
        $this->assertStringContainsString('venue deposit', $b->summary);
    }

    public function test_composer_is_idempotent_per_day(): void
    {
        $user = User::factory()->create();
        $composer = app(BriefingComposer::class);

        $composer->composeFor($user, $user->currentWorkspace());
        $composer->composeFor($user, $user->currentWorkspace());

        $this->assertSame(1, \App\Models\DailyBriefing::query()->where('user_id', $user->id)->count());
    }
}
