<?php

namespace Tests\Feature\Calendar;

use App\Enums\IntegrationProvider;
use App\Models\CalendarEvent;
use App\Models\IntegrationAccount;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * One commitment, one row.
 *
 * A meeting pushed to a connected calendar comes back on the next sync as a
 * calendar_events row pointing at it, and a task scheduled as a meeting exists
 * in both tables. Both used to be drawn twice.
 */
class CalendarDeduplicationTest extends TestCase
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

    protected function account(): IntegrationAccount
    {
        return IntegrationAccount::factory()
            ->provider(IntegrationProvider::Gmail)
            ->create(['user_id' => $this->user->id, 'workspace_id' => $this->workspace->id]);
    }

    protected function whenInMonth(int $day, int $hour = 9): CarbonImmutable
    {
        return now()->startOfMonth()->addDays($day)->setTime($hour, 0);
    }

    public function test_a_synced_copy_of_a_local_meeting_is_not_drawn_twice(): void
    {
        $meeting = Meeting::factory()->for($this->workspace)->create([
            'host_id' => $this->user->id,
            'title' => 'Sprint review',
            'starts_at' => $this->whenInMonth(5),
            'ends_at' => $this->whenInMonth(5, 10),
        ]);

        // What a push followed by a pull leaves behind.
        CalendarEvent::create([
            'workspace_id' => $this->workspace->id,
            'integration_account_id' => $this->account()->id,
            'meeting_id' => $meeting->id,
            'external_id' => 'gcal-sprint-review',
            'title' => 'Sprint review',
            'starts_at' => $this->whenInMonth(5),
            'ends_at' => $this->whenInMonth(5, 10),
        ]);

        $this->actingAs($this->user)
            ->get(route('calendar.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('events', 1)
                ->where('events.0.source', 'cadence')
                ->where('events.0.title', 'Sprint review')
            );
    }

    public function test_a_synced_copy_matched_only_by_provider_id_is_not_drawn_twice(): void
    {
        // Rows written before meeting_id was linked still carry the provider id
        // on the meeting itself.
        $meeting = Meeting::factory()->for($this->workspace)->create([
            'host_id' => $this->user->id,
            'title' => 'Legacy meeting',
            'starts_at' => $this->whenInMonth(6),
            'ends_at' => $this->whenInMonth(6, 10),
            'external_event_id' => 'gcal-legacy',
        ]);

        CalendarEvent::create([
            'workspace_id' => $this->workspace->id,
            'integration_account_id' => $this->account()->id,
            'external_id' => 'gcal-legacy',
            'title' => 'Legacy meeting',
            'starts_at' => $this->whenInMonth(6),
            'ends_at' => $this->whenInMonth(6, 10),
        ]);

        $this->assertNotNull($meeting->external_event_id);

        $this->actingAs($this->user)
            ->get(route('calendar.index'))
            ->assertInertia(fn (Assert $page) => $page->has('events', 1));
    }

    public function test_a_genuinely_external_event_is_still_shown(): void
    {
        CalendarEvent::create([
            'workspace_id' => $this->workspace->id,
            'integration_account_id' => $this->account()->id,
            'external_id' => 'gcal-dentist',
            'title' => 'Dentist',
            'starts_at' => $this->whenInMonth(8),
            'ends_at' => $this->whenInMonth(8, 10),
        ]);

        $this->actingAs($this->user)
            ->get(route('calendar.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('events', 1)
                ->where('events.0.source', 'external')
                ->where('events.0.title', 'Dentist')
            );
    }

    public function test_a_task_with_a_due_date_appears_once_as_a_task(): void
    {
        $project = Project::factory()->for($this->workspace)->create(['created_by' => $this->user->id]);

        Task::factory()->for($project)->create([
            'title' => 'Write the report',
            'created_by' => $this->user->id,
            'due_date' => $this->whenInMonth(12)->toDateString(),
        ]);

        $this->actingAs($this->user)
            ->get(route('calendar.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('events', 1)
                ->where('events.0.source', 'task')
                ->where('events.0.all_day', true)
                ->where('events.0.title', 'Write the report')
            );
    }

    public function test_a_scheduled_task_is_drawn_as_its_meeting_and_not_also_as_a_task(): void
    {
        $project = Project::factory()->for($this->workspace)->create(['created_by' => $this->user->id]);

        $meeting = Meeting::factory()->for($this->workspace)->create([
            'host_id' => $this->user->id,
            'title' => 'Design review',
            'starts_at' => $this->whenInMonth(14),
            'ends_at' => $this->whenInMonth(14, 10),
        ]);

        $task = Task::factory()->for($project)->create([
            'title' => 'Design review',
            'created_by' => $this->user->id,
            'due_date' => $this->whenInMonth(14)->toDateString(),
            'meeting_id' => $meeting->id,
        ]);

        $this->actingAs($this->user)
            ->get(route('calendar.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('events', 1)
                ->where('events.0.source', 'cadence')
                ->where('events.0.task_id', $task->id)
            );
    }

    public function test_occurrences_of_a_recurring_task_are_hidden_once_the_parent_is_scheduled(): void
    {
        $project = Project::factory()->for($this->workspace)->create(['created_by' => $this->user->id]);

        $meeting = Meeting::factory()->for($this->workspace)->create([
            'host_id' => $this->user->id,
            'title' => 'Weekly sync',
            'starts_at' => $this->whenInMonth(2),
            'ends_at' => $this->whenInMonth(2, 10),
            'recurrence_rule' => 'weekly',
        ]);

        $parent = Task::factory()->for($project)->create([
            'title' => 'Weekly sync',
            'created_by' => $this->user->id,
            'due_date' => $this->whenInMonth(2)->toDateString(),
            'recurrence_rule' => 'weekly',
            'meeting_id' => $meeting->id,
        ]);

        // The nightly command spawns these; the provider already expands the
        // series from one RRULE event, so drawing them would repeat it.
        foreach ([9, 16, 23] as $day) {
            Task::factory()->for($project)->create([
                'title' => 'Weekly sync',
                'created_by' => $this->user->id,
                'due_date' => $this->whenInMonth($day)->toDateString(),
                'recurrence_parent_id' => $parent->id,
            ]);
        }

        $this->actingAs($this->user)
            ->get(route('calendar.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('events', 1)
                ->where('events.0.source', 'cadence')
                ->where('events.0.recurring', true)
            );
    }
}
