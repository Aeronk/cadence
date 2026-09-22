<?php

namespace Tests\Feature\Integrations;

use App\Enums\IntegrationProvider;
use App\Integrations\IntegrationManager;
use App\Integrations\Providers\Gmail\GmailProvider;
use App\Jobs\SyncIntegrationAccountCalendar;
use App\Models\CalendarEvent;
use App\Models\CalendarSource;
use App\Models\IntegrationAccount;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CalendarSyncTest extends TestCase
{
    use RefreshDatabase;

    protected const CALENDAR_LIST_URL = 'googleapis.com/calendar/v3/users/me/calendarList*';

    protected function setUp(): void
    {
        parent::setUp();
        app(IntegrationManager::class)->bind(IntegrationProvider::Gmail, GmailProvider::class);
    }

    /**
     * A CalendarList response with one selected primary calendar, which is what
     * an account that has never been discovered looks like on first sync.
     *
     * @param  array<int, array<string, mixed>>  $extra
     */
    protected function calendarList(array $extra = []): PromiseInterface
    {
        return Http::response([
            'items' => array_merge([[
                'id' => 'primary',
                'summary' => 'Personal',
                'accessRole' => 'owner',
                'primary' => true,
                'selected' => true,
                'backgroundColor' => '#4285f4',
                'timeZone' => 'Africa/Harare',
            ]], $extra),
        ]);
    }

    public function test_google_calendar_sync_persists_events(): void
    {
        Http::fake([
            self::CALENDAR_LIST_URL => $this->calendarList(),
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

        // An account with no calendars discovers them rather than reporting zero
        // events and leaving nobody any the wiser.
        $calendar = CalendarSource::firstWhere('integration_account_id', $account->id);
        $this->assertSame('Personal', $calendar->name);
        $this->assertTrue($calendar->is_primary);

        // The sync token belongs to the calendar. It used to be written to the
        // account, where the Gmail inbox sync overwrote it with a date and every
        // calendar sync silently degraded to a full re-pull.
        $this->assertSame('sync-token-1', $calendar->sync_cursor);
    }

    public function test_cancelled_event_is_removed_on_sync(): void
    {
        Http::fake([
            self::CALENDAR_LIST_URL => $this->calendarList(),
            'googleapis.com/calendar/v3/calendars/primary/events*' => Http::response([
                'items' => [
                    ['id' => 'evt-1', 'status' => 'cancelled'],
                ],
                'nextSyncToken' => 'sync-token-2',
            ]),
        ]);

        $account = IntegrationAccount::factory()->provider(IntegrationProvider::Gmail)->create();
        $calendar = CalendarSource::create([
            'integration_account_id' => $account->id,
            'workspace_id' => $account->workspace_id,
            'external_id' => 'primary',
            'name' => 'Personal',
            'is_selected' => true,
        ]);

        CalendarEvent::create([
            'workspace_id' => $account->workspace_id,
            'integration_account_id' => $account->id,
            'calendar_source_id' => $calendar->id,
            'external_id' => 'evt-1',
            'title' => 'Doomed event',
            'starts_at' => now(),
            'ends_at' => now()->addHour(),
        ]);

        SyncIntegrationAccountCalendar::dispatchSync($account->id);

        $this->assertDatabaseMissing('calendar_events', ['external_id' => 'evt-1']);
    }

    public function test_every_selected_calendar_is_pulled_and_unselected_ones_are_left_alone(): void
    {
        Http::fake([
            self::CALENDAR_LIST_URL => $this->calendarList(),
            'googleapis.com/calendar/v3/calendars/primary/events*' => Http::response([
                'items' => [[
                    'id' => 'evt-personal',
                    'summary' => 'Dentist',
                    'start' => ['dateTime' => '2026-06-01T09:00:00Z'],
                    'end' => ['dateTime' => '2026-06-01T10:00:00Z'],
                ]],
            ]),
            'googleapis.com/calendar/v3/calendars/team%40example.com/events*' => Http::response([
                'items' => [[
                    'id' => 'evt-team',
                    'summary' => 'Sprint review',
                    'start' => ['dateTime' => '2026-06-01T11:00:00Z'],
                    'end' => ['dateTime' => '2026-06-01T12:00:00Z'],
                ]],
            ]),
            'googleapis.com/calendar/v3/calendars/holidays%40example.com/events*' => Http::response([
                'items' => [['id' => 'evt-holiday', 'summary' => 'Should never be pulled']],
            ]),
        ]);

        $account = IntegrationAccount::factory()->provider(IntegrationProvider::Gmail)->create();

        foreach ([
            ['primary', 'Personal', true],
            ['team@example.com', 'Team', true],
            ['holidays@example.com', 'Holidays', false],
        ] as [$externalId, $name, $selected]) {
            CalendarSource::create([
                'integration_account_id' => $account->id,
                'workspace_id' => $account->workspace_id,
                'external_id' => $externalId,
                'name' => $name,
                'is_selected' => $selected,
            ]);
        }

        SyncIntegrationAccountCalendar::dispatchSync($account->id);

        $this->assertDatabaseHas('calendar_events', ['external_id' => 'evt-personal']);
        $this->assertDatabaseHas('calendar_events', ['external_id' => 'evt-team']);
        // Unticking a calendar has to mean Cadence stops asking for it.
        $this->assertDatabaseMissing('calendar_events', ['external_id' => 'evt-holiday']);
    }

    public function test_one_failing_calendar_does_not_stop_the_others(): void
    {
        Http::fake([
            self::CALENDAR_LIST_URL => $this->calendarList(),
            'googleapis.com/calendar/v3/calendars/broken%40example.com/events*' => Http::response(
                ['error' => ['code' => 403, 'message' => 'Access denied']],
                403,
            ),
            'googleapis.com/calendar/v3/calendars/primary/events*' => Http::response([
                'items' => [[
                    'id' => 'evt-survivor',
                    'summary' => 'Still here',
                    'start' => ['dateTime' => '2026-06-01T09:00:00Z'],
                    'end' => ['dateTime' => '2026-06-01T10:00:00Z'],
                ]],
            ]),
        ]);

        $account = IntegrationAccount::factory()->provider(IntegrationProvider::Gmail)->create();

        $broken = CalendarSource::create([
            'integration_account_id' => $account->id,
            'workspace_id' => $account->workspace_id,
            'external_id' => 'broken@example.com',
            'name' => 'Revoked',
            'is_selected' => true,
        ]);
        CalendarSource::create([
            'integration_account_id' => $account->id,
            'workspace_id' => $account->workspace_id,
            'external_id' => 'primary',
            'name' => 'Personal',
            'is_selected' => true,
        ]);

        SyncIntegrationAccountCalendar::dispatchSync($account->id);

        // Losing access to one shared calendar must not take the whole account's
        // sync down with it.
        $this->assertDatabaseHas('calendar_events', ['external_id' => 'evt-survivor']);
        $this->assertNotNull($broken->fresh()->last_error);
        $this->assertNull($account->fresh()->last_error);
    }

    public function test_the_calendar_list_keeps_a_choice_the_user_has_already_made(): void
    {
        Http::fake([
            self::CALENDAR_LIST_URL => $this->calendarList([[
                'id' => 'team@example.com',
                'summary' => 'Team',
                'accessRole' => 'writer',
                // Hidden in Google, but the user turned it on in Cadence.
                'selected' => false,
            ]]),
        ]);

        $account = IntegrationAccount::factory()->provider(IntegrationProvider::Gmail)->create();

        $chosen = CalendarSource::create([
            'integration_account_id' => $account->id,
            'workspace_id' => $account->workspace_id,
            'external_id' => 'team@example.com',
            'name' => 'Team',
            'is_selected' => true,
        ]);

        app(GmailProvider::class)->syncCalendarList($account);

        // A refresh re-reads names and colours; it must not overrule the user.
        $this->assertTrue($chosen->fresh()->is_selected);
    }

    public function test_a_calendar_the_account_lost_access_to_is_dropped(): void
    {
        Http::fake([self::CALENDAR_LIST_URL => $this->calendarList()]);

        $account = IntegrationAccount::factory()->provider(IntegrationProvider::Gmail)->create();

        $gone = CalendarSource::create([
            'integration_account_id' => $account->id,
            'workspace_id' => $account->workspace_id,
            'external_id' => 'unshared@example.com',
            'name' => 'No longer shared',
            'is_selected' => true,
        ]);

        app(GmailProvider::class)->syncCalendarList($account);

        $this->assertDatabaseMissing('calendar_sources', ['id' => $gone->id]);
        $this->assertDatabaseHas('calendar_sources', ['external_id' => 'primary']);
    }

    public function test_the_primary_calendar_becomes_the_write_target(): void
    {
        Http::fake([
            self::CALENDAR_LIST_URL => $this->calendarList([[
                'id' => 'team@example.com',
                'summary' => 'Team',
                'accessRole' => 'writer',
                'selected' => true,
            ]]),
        ]);

        $account = IntegrationAccount::factory()->provider(IntegrationProvider::Gmail)->create();

        app(GmailProvider::class)->syncCalendarList($account);

        // A push has to know where to write without guessing.
        $target = $account->writeTargetCalendar();
        $this->assertSame('primary', $target->external_id);
        $this->assertTrue($target->is_write_target);
    }
}
