<?php

namespace Tests\Feature\Integrations;

use App\Enums\IntegrationProvider;
use App\Integrations\IntegrationManager;
use App\Integrations\Providers\Gmail\GmailProvider;
use App\Jobs\PushMeetingToCalendar;
use App\Models\IntegrationAccount;
use App\Models\Meeting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CalendarPushTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(IntegrationManager::class)->bind(IntegrationProvider::Gmail, GmailProvider::class);
    }

    public function test_creating_a_meeting_dispatches_push_job(): void
    {
        Bus::fake();

        $host = User::factory()->create();

        Meeting::factory()->for($host->currentWorkspace())->create(['host_id' => $host->id]);

        Bus::assertDispatched(PushMeetingToCalendar::class, fn ($job) => $job->action === PushMeetingToCalendar::ACTION_CREATE);
    }

    public function test_deleting_a_meeting_dispatches_delete_job(): void
    {
        Bus::fake();

        $host = User::factory()->create();
        $meeting = Meeting::factory()->for($host->currentWorkspace())->create(['host_id' => $host->id]);

        $meeting->delete();

        Bus::assertDispatched(PushMeetingToCalendar::class, fn ($job) => $job->action === PushMeetingToCalendar::ACTION_DELETE);
    }

    public function test_push_creates_external_event_via_google(): void
    {
        Bus::fake(); // suppress observer auto-dispatch so we control the job invocation

        Http::fake([
            'googleapis.com/calendar/v3/calendars/primary/events' => Http::response([
                'id' => 'gcal-event-1',
                'etag' => '"v1"',
            ]),
        ]);

        $host = User::factory()->create();
        $workspace = $host->currentWorkspace();

        IntegrationAccount::factory()
            ->provider(IntegrationProvider::Gmail)
            ->create(['user_id' => $host->id, 'workspace_id' => $workspace->id]);

        $meeting = Meeting::factory()->for($workspace)->create(['host_id' => $host->id]);

        (new PushMeetingToCalendar($meeting->id, PushMeetingToCalendar::ACTION_CREATE))
            ->handle(app(IntegrationManager::class));

        $this->assertDatabaseHas('calendar_events', [
            'meeting_id' => $meeting->id,
            'external_id' => 'gcal-event-1',
        ]);
    }

    public function test_push_is_a_noop_without_connected_account(): void
    {
        $host = User::factory()->create();
        $meeting = Meeting::factory()->for($host->currentWorkspace())->create(['host_id' => $host->id]);

        // No IntegrationAccount for this user.
        (new PushMeetingToCalendar($meeting->id, PushMeetingToCalendar::ACTION_CREATE))
            ->handle(app(IntegrationManager::class));

        $this->assertDatabaseCount('calendar_events', 0);
    }
}
