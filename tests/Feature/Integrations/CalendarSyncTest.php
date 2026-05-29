<?php

namespace Tests\Feature\Integrations;

use App\Enums\IntegrationProvider;
use App\Integrations\IntegrationManager;
use App\Integrations\Providers\Gmail\GmailProvider;
use App\Jobs\SyncIntegrationAccountCalendar;
use App\Models\CalendarEvent;
use App\Models\IntegrationAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CalendarSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(IntegrationManager::class)->bind(IntegrationProvider::Gmail, GmailProvider::class);
    }

    public function test_google_calendar_sync_persists_events(): void
    {
        Http::fake([
            'googleapis.com/calendar/v3/calendars/primary/events*' => Http::response([
                'items' => [
                    [
                        'id' => 'evt-1',
                        'etag' => '"v1"',
                        'status' => 'confirmed',
                        'summary' => 'Standup',
                        'description' => 'Daily team sync',
                        'location' => 'Zoom',
                        'start' => ['dateTime' => '2026-06-01T09:00:00Z'],
                        'end' => ['dateTime' => '2026-06-01T09:15:00Z'],
                        'attendees' => [['email' => 'alice@example.com'], ['email' => 'bob@example.com']],
                    ],
                    [
                        'id' => 'evt-2',
                        'status' => 'confirmed',
                        'summary' => 'Design review',
                        'start' => ['dateTime' => '2026-06-02T14:00:00Z'],
                        'end' => ['dateTime' => '2026-06-02T15:00:00Z'],
                    ],
                ],
                'nextSyncToken' => 'sync-token-1',
            ]),
        ]);

        $account = IntegrationAccount::factory()->provider(IntegrationProvider::Gmail)->create();

        SyncIntegrationAccountCalendar::dispatchSync($account->id);

        $this->assertSame(2, CalendarEvent::query()->where('integration_account_id', $account->id)->count());
        $this->assertSame('sync-token-1', $account->fresh()->sync_cursor);
    }

    public function test_cancelled_event_is_removed_on_sync(): void
    {
        Http::fake([
            'googleapis.com/calendar/v3/calendars/primary/events*' => Http::response([
                'items' => [
                    ['id' => 'evt-1', 'status' => 'cancelled'],
                ],
                'nextSyncToken' => 'sync-token-2',
            ]),
        ]);

        $account = IntegrationAccount::factory()->provider(IntegrationProvider::Gmail)->create();
        CalendarEvent::create([
            'workspace_id' => $account->workspace_id,
            'integration_account_id' => $account->id,
            'external_id' => 'evt-1',
            'title' => 'Doomed event',
            'starts_at' => now(),
            'ends_at' => now()->addHour(),
        ]);

        SyncIntegrationAccountCalendar::dispatchSync($account->id);

        $this->assertDatabaseMissing('calendar_events', ['external_id' => 'evt-1']);
    }
}
