<?php

namespace Tests\Feature\Meetings;

use App\Models\Meeting;
use App\Models\User;
use App\Notifications\MeetingReminder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class MeetingReminderTest extends TestCase
{
    use RefreshDatabase;

    public function test_reminder_fires_within_window_for_attendees_and_host(): void
    {
        Notification::fake();

        $host = User::factory()->create();
        $attendee = User::factory()->create();
        $workspace = $host->currentWorkspace();
        $workspace->members()->attach($attendee, ['role' => 'member']);

        $meeting = Meeting::factory()->for($workspace)->create([
            'host_id' => $host->id,
            'starts_at' => now()->addMinutes(15),
            'ends_at' => now()->addMinutes(45),
            'reminder_minutes_before' => 15,
        ]);
        $meeting->attendees()->attach($attendee, ['rsvp_status' => 'accepted']);

        $this->artisan('meetings:send-reminders')->assertExitCode(0);

        Notification::assertSentTo($host, MeetingReminder::class);
        Notification::assertSentTo($attendee, MeetingReminder::class);

        $this->assertNotNull($meeting->fresh()->reminder_sent_at);
    }

    public function test_reminder_does_not_fire_outside_window(): void
    {
        Notification::fake();

        $host = User::factory()->create();
        Meeting::factory()->for($host->currentWorkspace())->create([
            'host_id' => $host->id,
            'starts_at' => now()->addHours(3),
            'ends_at' => now()->addHours(4),
            'reminder_minutes_before' => 15,
        ]);

        $this->artisan('meetings:send-reminders');

        Notification::assertNothingSentTo($host);
    }

    public function test_reminder_is_idempotent_across_runs(): void
    {
        Notification::fake();

        $host = User::factory()->create();
        $meeting = Meeting::factory()->for($host->currentWorkspace())->create([
            'host_id' => $host->id,
            'starts_at' => now()->addMinutes(10),
            'ends_at' => now()->addMinutes(40),
            'reminder_minutes_before' => 10,
        ]);

        $this->artisan('meetings:send-reminders');
        $this->artisan('meetings:send-reminders');

        Notification::assertSentToTimes($host, MeetingReminder::class, 1);
    }

    public function test_changing_start_time_resets_reminder(): void
    {
        $host = User::factory()->create();
        $meeting = Meeting::factory()->for($host->currentWorkspace())->create([
            'host_id' => $host->id,
            'starts_at' => now()->addHour(),
            'ends_at' => now()->addHours(2),
            'reminder_minutes_before' => 15,
            'reminder_sent_at' => now(),
        ]);

        $this->actingAs($host)->patch(route('meetings.update', $meeting), [
            'title' => $meeting->title,
            'starts_at' => now()->addHours(3)->toDateTimeString(),
            'ends_at' => now()->addHours(4)->toDateTimeString(),
        ])->assertRedirect();

        $this->assertNull($meeting->fresh()->reminder_sent_at);
    }
}
