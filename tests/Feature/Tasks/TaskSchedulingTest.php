<?php

namespace Tests\Feature\Tasks;

use App\Models\Meeting;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\MeetingInvited;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TaskSchedulingTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected Workspace $workspace;

    protected Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->workspace = $this->owner->currentWorkspace();
        $this->project = Project::factory()->for($this->workspace)->create(['created_by' => $this->owner->id]);
    }

    protected function task(array $attributes = []): Task
    {
        return Task::factory()->for($this->project)->create($attributes + [
            'created_by' => $this->owner->id,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function payload(array $overrides = []): array
    {
        return array_merge([
            'starts_at' => now()->addDay()->setTime(10, 0)->toDateTimeString(),
            'ends_at' => now()->addDay()->setTime(11, 0)->toDateTimeString(),
            'meeting_type' => 'online',
        ], $overrides);
    }

    public function test_scheduling_a_task_creates_a_linked_meeting(): void
    {
        Notification::fake();

        $task = $this->task(['title' => 'Kickoff']);

        $this->actingAs($this->owner)
            ->post(route('tasks.schedule.store', $task), $this->payload())
            ->assertRedirect();

        $task->refresh();
        $this->assertNotNull($task->meeting_id);

        $meeting = $task->meeting;
        $this->assertSame('Kickoff', $meeting->title);
        $this->assertSame($this->owner->id, $meeting->host_id);
        $this->assertSame($this->project->id, $meeting->project_id);
        $this->assertSame($this->workspace->id, $meeting->workspace_id);
    }

    public function test_scheduling_twice_updates_the_same_meeting_rather_than_making_another(): void
    {
        Notification::fake();

        $task = $this->task();

        $this->actingAs($this->owner)
            ->post(route('tasks.schedule.store', $task), $this->payload())
            ->assertRedirect();

        $firstMeetingId = $task->fresh()->meeting_id;

        $this->actingAs($this->owner)
            ->post(route('tasks.schedule.store', $task), $this->payload([
                'starts_at' => now()->addDays(2)->setTime(14, 0)->toDateTimeString(),
                'ends_at' => now()->addDays(2)->setTime(15, 0)->toDateTimeString(),
            ]))
            ->assertRedirect();

        $this->assertSame($firstMeetingId, $task->fresh()->meeting_id);
        $this->assertSame(1, Meeting::count());
        $this->assertSame(
            now()->addDays(2)->setTime(14, 0)->toDateTimeString(),
            $task->fresh()->meeting->starts_at->toDateTimeString(),
        );
    }

    public function test_attendees_are_invited(): void
    {
        Notification::fake();

        $colleague = User::factory()->create();
        $this->workspace->members()->attach($colleague, ['role' => 'member']);

        $task = $this->task();

        $this->actingAs($this->owner)
            ->post(route('tasks.schedule.store', $task), $this->payload([
                'attendee_ids' => [$colleague->id],
            ]))
            ->assertRedirect();

        $meeting = $task->fresh()->meeting;
        $this->assertTrue($meeting->attendees()->whereKey($colleague->id)->exists());
        $this->assertSame('pending', $meeting->attendees()->first()->pivot->rsvp_status);

        Notification::assertSentTo($colleague, MeetingInvited::class);
    }

    public function test_attendees_default_to_the_tasks_assignees(): void
    {
        Notification::fake();

        $assignee = User::factory()->create();
        $this->workspace->members()->attach($assignee, ['role' => 'member']);

        $task = $this->task();
        $task->assignees()->attach($assignee);

        $this->actingAs($this->owner)
            ->post(route('tasks.schedule.store', $task), $this->payload())
            ->assertRedirect();

        $this->assertTrue(
            $task->fresh()->meeting->attendees()->whereKey($assignee->id)->exists(),
        );
    }

    public function test_someone_outside_the_workspace_cannot_be_added_as_an_attendee(): void
    {
        Notification::fake();

        $outsider = User::factory()->create();
        $task = $this->task();

        $this->actingAs($this->owner)
            ->post(route('tasks.schedule.store', $task), $this->payload([
                'attendee_ids' => [$outsider->id],
            ]))
            ->assertRedirect();

        $this->assertFalse(
            $task->fresh()->meeting->attendees()->whereKey($outsider->id)->exists(),
        );
    }

    public function test_a_repeating_task_carries_its_rule_onto_the_meeting(): void
    {
        Notification::fake();

        $task = $this->task([
            'recurrence_rule' => 'weekly',
            'recurrence_ends_on' => now()->addMonths(2)->toDateString(),
        ]);

        $this->actingAs($this->owner)
            ->post(route('tasks.schedule.store', $task), $this->payload(['repeat' => true]))
            ->assertRedirect();

        $meeting = $task->fresh()->meeting;
        $this->assertSame('weekly', $meeting->recurrence_rule);
        $this->assertTrue($meeting->isRecurring());

        // One event carrying an RRULE, rather than one event per occurrence.
        $rules = $meeting->recurrenceRules();
        $this->assertCount(1, $rules);
        $this->assertStringContainsString('FREQ=WEEKLY', $rules[0]);
        $this->assertStringContainsString('UNTIL=', $rules[0]);
    }

    public function test_declining_to_repeat_leaves_the_meeting_as_a_one_off(): void
    {
        Notification::fake();

        $task = $this->task(['recurrence_rule' => 'weekly']);

        $this->actingAs($this->owner)
            ->post(route('tasks.schedule.store', $task), $this->payload(['repeat' => false]))
            ->assertRedirect();

        $meeting = $task->fresh()->meeting;
        $this->assertNull($meeting->recurrence_rule);
        $this->assertNull($meeting->recurrenceRules());
    }

    public function test_requesting_a_conference_link_is_recorded(): void
    {
        Notification::fake();

        $task = $this->task();

        $this->actingAs($this->owner)
            ->post(route('tasks.schedule.store', $task), $this->payload(['create_conference' => true]))
            ->assertRedirect();

        $this->assertTrue($task->fresh()->meeting->conference_requested);
    }

    public function test_unscheduling_removes_the_meeting_and_the_link(): void
    {
        Notification::fake();

        $task = $this->task();

        $this->actingAs($this->owner)
            ->post(route('tasks.schedule.store', $task), $this->payload())
            ->assertRedirect();

        $meetingId = $task->fresh()->meeting_id;

        $this->actingAs($this->owner)
            ->delete(route('tasks.schedule.destroy', $task))
            ->assertRedirect();

        $this->assertNull($task->fresh()->meeting_id);
        $this->assertSoftDeleted('meetings', ['id' => $meetingId]);
    }

    public function test_unscheduling_an_unscheduled_task_reports_an_error(): void
    {
        $task = $this->task();

        $this->actingAs($this->owner)
            ->from(route('tasks.show', $task))
            ->delete(route('tasks.schedule.destroy', $task))
            ->assertSessionHas('flash.error');
    }

    public function test_the_end_time_must_follow_the_start(): void
    {
        $task = $this->task();

        $this->actingAs($this->owner)
            ->post(route('tasks.schedule.store', $task), $this->payload([
                'ends_at' => now()->addDay()->setTime(9, 0)->toDateTimeString(),
            ]))
            ->assertSessionHasErrors('ends_at');
    }

    public function test_the_task_page_carries_what_the_edit_and_schedule_forms_need(): void
    {
        $task = $this->task();

        $this->actingAs($this->owner)
            ->get(route('tasks.show', $task))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Tasks/Show')
                // The category list was already sent but unused by the dialog.
                ->has('categories')
                ->has('statuses')
                ->has('priorities')
                ->has('assignable_users')
                ->has('recurrence_options')
                ->has('milestones_for_select')
            );
    }
}
