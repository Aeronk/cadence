<?php

namespace Tests\Feature\Meetings;

use App\Models\Meeting;
use App\Models\User;
use App\Notifications\MeetingInvited;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MeetingCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_schedule_a_meeting_with_attendees(): void
    {
        Notification::fake();

        $host = User::factory()->create();
        $workspace = $host->currentWorkspace();
        $attendee = User::factory()->create();
        $workspace->members()->attach($attendee, ['role' => 'member']);

        $this->actingAs($host)->post(route('meetings.store'), [
            'title' => 'Kickoff',
            'starts_at' => now()->addDay()->toDateTimeString(),
            'ends_at' => now()->addDay()->addHour()->toDateTimeString(),
            'attendee_ids' => [$attendee->id],
        ])->assertRedirect();

        $this->assertDatabaseHas('meetings', [
            'workspace_id' => $workspace->id,
            'host_id' => $host->id,
            'title' => 'Kickoff',
        ]);

        Notification::assertSentTo($attendee, MeetingInvited::class);
    }

    public function test_attendee_can_view_meeting(): void
    {
        $host = User::factory()->create();
        $workspace = $host->currentWorkspace();
        $attendee = User::factory()->create();
        $workspace->members()->attach($attendee, ['role' => 'member']);

        $meeting = Meeting::factory()->for($workspace)->create(['host_id' => $host->id]);
        $meeting->attendees()->attach($attendee);

        $this->actingAs($attendee)
            ->get(route('meetings.show', $meeting))
            ->assertInertia(fn (Assert $page) => $page->component('Meetings/Show'));
    }

    public function test_outsider_cannot_view_meeting(): void
    {
        $meeting = Meeting::factory()->create();
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->get(route('meetings.show', $meeting))
            ->assertForbidden();
    }

    public function test_host_can_cancel_meeting(): void
    {
        $host = User::factory()->create();
        $meeting = Meeting::factory()->for($host->currentWorkspace())->create(['host_id' => $host->id]);

        $this->actingAs($host)
            ->delete(route('meetings.destroy', $meeting))
            ->assertRedirect(route('meetings.index'));

        $this->assertSoftDeleted('meetings', ['id' => $meeting->id]);
    }
}
