<?php

namespace Tests\Unit\Models;

use App\Models\Meeting;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeetingTest extends TestCase
{
    use RefreshDatabase;

    public function test_meeting_belongs_to_workspace_and_host(): void
    {
        $workspace = Workspace::factory()->create();
        $host = User::factory()->create();
        $meeting = Meeting::factory()->for($workspace)->create(['host_id' => $host->id]);

        $this->assertTrue($meeting->workspace->is($workspace));
        $this->assertTrue($meeting->host->is($host));
    }

    public function test_attendees_pivot_with_rsvp(): void
    {
        $meeting = Meeting::factory()->create();
        $user = User::factory()->create();

        $meeting->attendees()->attach($user, ['rsvp_status' => 'accepted']);

        $pivot = $meeting->attendees()->where('users.id', $user->id)->first()->pivot;
        $this->assertSame('accepted', $pivot->rsvp_status);
    }

    public function test_has_attendee_includes_host(): void
    {
        $host = User::factory()->create();
        $meeting = Meeting::factory()->create(['host_id' => $host->id]);

        $this->assertTrue($meeting->hasAttendee($host));
    }
}
