<?php

namespace Tests\Feature\Integrations;

use App\Enums\IntegrationProvider;
use App\Integrations\IntegrationManager;
use App\Integrations\Providers\Gmail\GmailProvider;
use App\Jobs\SyncIntegrationAccountCalendar;
use App\Models\CalendarEvent;
use App\Models\IntegrationAccount;
use App\Models\Meeting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleCalendarSyncTest extends TestCase
{
    use RefreshDatabase;

    protected const EVENTS_URL = 'https://www.googleapis.com/calendar/v3/calendars/primary/events';

    protected User $user;

    protected IntegrationAccount $account;

    protected function setUp(): void
    {
        parent::setUp();

        app(IntegrationManager::class)->bind(IntegrationProvider::Gmail, GmailProvider::class);

        $this->user = User::factory()->create();
        $this->account = IntegrationAccount::factory()
            ->provider(IntegrationProvider::Gmail)
            ->create([
                'user_id' => $this->user->id,
                'workspace_id' => $this->user->currentWorkspace()->id,
            ]);
    }

    protected function provider(): GmailProvider
    {
        return app(GmailProvider::class);
    }

    public function test_a_full_sync_stores_the_fields_the_calendar_needs(): void
    {
        Http::fake([
            self::EVENTS_URL.'*' => Http::response([
                'items' => [[
                    'id' => 'evt-1',
                    'etag' => '"v1"',
                    'summary' => 'Quarterly review',
                    'description' => 'Numbers',
                    'location' => 'Room 3',
                    'htmlLink' => 'https://calendar.google.com/evt-1',
                    'hangoutLink' => 'https://meet.google.com/abc-defg-hij',
                    'organizer' => ['email' => 'boss@example.com'],
                    'start' => ['dateTime' => '2026-10-01T09:00:00+02:00'],
                    'end' => ['dateTime' => '2026-10-01T10:00:00+02:00'],
                    'attendees' => [
                        ['email' => 'a@example.com', 'displayName' => 'Ada', 'responseStatus' => 'accepted'],
                        ['email' => 'b@example.com', 'responseStatus' => 'needsAction'],
                        ['displayName' => 'No address'],
                    ],
                ]],
                'nextSyncToken' => 'token-1',
            ]),
        ]);

        $count = $this->provider()->syncEvents($this->account);

        $this->assertSame(1, $count);

        $event = CalendarEvent::firstWhere('external_id', 'evt-1');
        $this->assertSame('Quarterly review', $event->title);
        $this->assertSame('boss@example.com', $event->organizer_email);
        $this->assertSame('https://calendar.google.com/evt-1', $event->html_link);
        $this->assertSame('https://meet.google.com/abc-defg-hij', $event->conference_url);
        $this->assertFalse($event->all_day);

        // Response status is kept, not flattened to a bare address, and an
        // attendee with no address is dropped.
        $this->assertCount(2, $event->attendees);
        $this->assertSame('accepted', $event->attendees[0]['response_status']);
        $this->assertSame(['a@example.com', 'b@example.com'], $event->attendeeEmails());

        $this->assertSame('token-1', $this->account->fresh()->sync_cursor);
    }

    public function test_an_all_day_event_is_flagged(): void
    {
        Http::fake([
            self::EVENTS_URL.'*' => Http::response([
                'items' => [[
                    'id' => 'evt-holiday',
                    'summary' => 'Public holiday',
                    'start' => ['date' => '2026-10-05'],
                    'end' => ['date' => '2026-10-06'],
                ]],
            ]),
        ]);

        $this->provider()->syncEvents($this->account);

        $this->assertTrue(CalendarEvent::firstWhere('external_id', 'evt-holiday')->all_day);
    }

    public function test_an_instance_of_a_series_records_its_master(): void
    {
        Http::fake([
            self::EVENTS_URL.'*' => Http::response([
                'items' => [[
                    'id' => 'evt-series_20261001',
                    'summary' => 'Weekly sync',
                    'recurringEventId' => 'evt-series',
                    'start' => ['dateTime' => '2026-10-01T09:00:00+02:00'],
                    'end' => ['dateTime' => '2026-10-01T09:30:00+02:00'],
                ]],
            ]),
        ]);

        $this->provider()->syncEvents($this->account);

        $this->assertSame(
            'evt-series',
            CalendarEvent::firstWhere('external_id', 'evt-series_20261001')->recurring_event_id,
        );
    }

    public function test_an_expired_sync_token_is_recovered_from_rather_than_breaking_sync_forever(): void
    {
        $this->account->forceFill(['sync_cursor' => 'stale-token'])->save();

        $calls = 0;

        Http::fake([
            self::EVENTS_URL.'*' => function () use (&$calls) {
                $calls++;

                // Google answers 410 GONE once the token has aged out.
                if ($calls === 1) {
                    return Http::response(['error' => ['code' => 410]], 410);
                }

                return Http::response([
                    'items' => [[
                        'id' => 'evt-after-reset',
                        'summary' => 'Recovered',
                        'start' => ['dateTime' => '2026-10-02T09:00:00+02:00'],
                        'end' => ['dateTime' => '2026-10-02T10:00:00+02:00'],
                    ]],
                    'nextSyncToken' => 'fresh-token',
                ]);
            },
        ]);

        $count = $this->provider()->syncEvents($this->account);

        $this->assertSame(2, $calls);
        $this->assertSame(1, $count);
        $this->assertDatabaseHas('calendar_events', ['external_id' => 'evt-after-reset']);
        $this->assertSame('fresh-token', $this->account->fresh()->sync_cursor);

        // The retry drops the token and asks for a window instead.
        Http::assertSent(fn ($request) => str_contains($request->url(), 'timeMin='));
    }

    public function test_an_incremental_sync_keeps_single_events_so_the_token_stays_valid(): void
    {
        $this->account->forceFill(['sync_cursor' => 'good-token'])->save();

        Http::fake([self::EVENTS_URL.'*' => Http::response(['items' => []])]);

        $this->provider()->syncEvents($this->account);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'syncToken=good-token')
            && str_contains($request->url(), 'singleEvents=true')
            // timeMin cannot be combined with a sync token.
            && ! str_contains($request->url(), 'timeMin'));
    }

    public function test_a_cancelled_event_is_removed(): void
    {
        CalendarEvent::create([
            'workspace_id' => $this->account->workspace_id,
            'integration_account_id' => $this->account->id,
            'external_id' => 'evt-gone',
            'title' => 'Was happening',
            'starts_at' => now(),
            'ends_at' => now()->addHour(),
        ]);

        Http::fake([
            self::EVENTS_URL.'*' => Http::response([
                'items' => [['id' => 'evt-gone', 'status' => 'cancelled']],
            ]),
        ]);

        $this->provider()->syncEvents($this->account);

        $this->assertDatabaseMissing('calendar_events', ['external_id' => 'evt-gone']);
    }

    public function test_a_sync_does_not_sever_the_link_to_a_local_meeting(): void
    {
        Bus::fake();

        $meeting = Meeting::factory()->for($this->user->currentWorkspace())->create([
            'host_id' => $this->user->id,
        ]);

        CalendarEvent::create([
            'workspace_id' => $this->account->workspace_id,
            'integration_account_id' => $this->account->id,
            'meeting_id' => $meeting->id,
            'external_id' => 'evt-ours',
            'title' => 'Ours',
            'starts_at' => now(),
            'ends_at' => now()->addHour(),
        ]);

        Http::fake([
            self::EVENTS_URL.'*' => Http::response([
                'items' => [[
                    'id' => 'evt-ours',
                    'summary' => 'Ours, retitled upstream',
                    'start' => ['dateTime' => '2026-10-03T09:00:00+02:00'],
                    'end' => ['dateTime' => '2026-10-03T10:00:00+02:00'],
                ]],
            ]),
        ]);

        $this->provider()->syncEvents($this->account);

        $event = CalendarEvent::firstWhere('external_id', 'evt-ours');
        // Losing this would make the meeting show up twice on the calendar.
        $this->assertSame($meeting->id, $event->meeting_id);
        $this->assertSame('Ours, retitled upstream', $event->title);
    }

    public function test_creating_an_event_asks_google_to_email_the_attendees(): void
    {
        Bus::fake();

        Http::fake([self::EVENTS_URL.'*' => Http::response(['id' => 'evt-new', 'etag' => '"v1"'])]);

        $meeting = Meeting::factory()->for($this->user->currentWorkspace())->create([
            'host_id' => $this->user->id,
        ]);

        $this->provider()->createEvent($this->account, $meeting);

        // Without sendUpdates the invitation is created silently and nobody
        // outside Cadence ever hears about the meeting.
        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && str_contains($request->url(), 'sendUpdates=all'));
    }

    public function test_a_repeating_meeting_is_pushed_as_one_event_with_a_recurrence_rule(): void
    {
        Bus::fake();

        Http::fake([self::EVENTS_URL.'*' => Http::response(['id' => 'evt-series', 'etag' => '"v1"'])]);

        $meeting = Meeting::factory()->for($this->user->currentWorkspace())->create([
            'host_id' => $this->user->id,
            'starts_at' => '2026-10-05 09:00:00',
            'ends_at' => '2026-10-05 09:30:00',
            'recurrence_rule' => 'weekly',
            'recurrence_ends_on' => '2026-12-31',
        ]);

        $this->provider()->createEvent($this->account, $meeting);

        Http::assertSent(function ($request) {
            $rules = $request->data()['recurrence'] ?? null;

            return is_array($rules)
                && count($rules) === 1
                && str_contains($rules[0], 'FREQ=WEEKLY')
                && str_contains($rules[0], 'UNTIL=2026');
        });
    }

    public function test_a_one_off_meeting_carries_no_recurrence(): void
    {
        Bus::fake();

        Http::fake([self::EVENTS_URL.'*' => Http::response(['id' => 'evt-once', 'etag' => '"v1"'])]);

        $meeting = Meeting::factory()->for($this->user->currentWorkspace())->create([
            'host_id' => $this->user->id,
        ]);

        $this->provider()->createEvent($this->account, $meeting);

        Http::assertSent(fn ($request) => ! array_key_exists('recurrence', $request->data()));
    }

    public function test_requesting_a_conference_asks_for_a_meet_link_and_stores_the_result(): void
    {
        Bus::fake();

        Http::fake([
            self::EVENTS_URL.'*' => Http::response([
                'id' => 'evt-meet',
                'etag' => '"v1"',
                'hangoutLink' => 'https://meet.google.com/xyz-1234-abc',
            ]),
        ]);

        $meeting = Meeting::factory()->for($this->user->currentWorkspace())->create([
            'host_id' => $this->user->id,
            'meeting_url' => null,
            'conference_requested' => true,
        ]);

        $this->provider()->createEvent($this->account, $meeting);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'conferenceDataVersion=1')
            && isset($request->data()['conferenceData']['createRequest']));

        $this->assertSame('https://meet.google.com/xyz-1234-abc', $meeting->fresh()->meeting_url);
    }

    public function test_deleting_an_event_already_gone_upstream_is_not_an_error(): void
    {
        Bus::fake();

        $event = CalendarEvent::create([
            'workspace_id' => $this->account->workspace_id,
            'integration_account_id' => $this->account->id,
            'external_id' => 'evt-vanished',
            'title' => 'Vanished',
            'starts_at' => now(),
            'ends_at' => now()->addHour(),
        ]);

        Http::fake([self::EVENTS_URL.'*' => Http::response(['error' => 'not found'], 404)]);

        $this->provider()->deleteEvent($this->account, $event);

        $this->assertDatabaseMissing('calendar_events', ['id' => $event->id]);
    }

    public function test_push_is_skipped_when_no_callback_is_configured(): void
    {
        config(['integrations.google.calendar_push_enabled' => false]);

        Http::fake();

        $this->provider()->watchCalendar($this->account);

        Http::assertNothingSent();
    }

    public function test_the_scheduled_command_queues_a_sync_per_connected_account(): void
    {
        Bus::fake();

        // A provider with no calendar is left alone.
        IntegrationAccount::factory()
            ->provider(IntegrationProvider::TwilioSms)
            ->create(['user_id' => $this->user->id, 'workspace_id' => $this->account->workspace_id]);

        $this->artisan('integrations:sync-calendars')->assertSuccessful();

        Bus::assertDispatchedTimes(SyncIntegrationAccountCalendar::class, 1);
    }

    /**
     * Register a channel secret the way watchCalendar does, and hand back the
     * plaintext token Google would echo on each notification.
     */
    protected function registerChannelSecret(string $secret = 's3cret-channel-value'): string
    {
        $this->account->forceFill([
            'settings' => array_merge($this->account->settings ?? [], [
                'calendar_channel_id' => 'cadence-cal-'.$this->account->id.'-abc',
                'calendar_channel_token_hash' => hash('sha256', $secret),
            ]),
        ])->save();

        return $this->account->id.'.'.$secret;
    }

    public function test_watching_a_calendar_sends_a_secret_token_and_stores_only_its_hash(): void
    {
        config([
            'integrations.google.calendar_push_enabled' => true,
            'integrations.google.calendar_webhook_url' => 'https://example.test/hook',
        ]);

        Http::fake([
            'googleapis.com/calendar/v3/calendars/primary/events/watch*' => Http::response([
                'resourceId' => 'res-1',
                'expiration' => (string) (now()->addDays(7)->getTimestamp() * 1000),
            ]),
        ]);

        $this->provider()->watchCalendar($this->account);

        $sentToken = null;
        Http::assertSent(function ($request) use (&$sentToken) {
            $sentToken = $request->data()['token'] ?? null;

            return $sentToken !== null;
        });

        [$id, $secret] = explode('.', $sentToken, 2);
        $this->assertSame((string) $this->account->id, $id);

        // The secret must be long enough not to be guessable, and must never be
        // stored in the clear.
        $this->assertGreaterThanOrEqual(32, strlen($secret));

        $settings = $this->account->fresh()->settings;
        $this->assertSame(hash('sha256', $secret), $settings['calendar_channel_token_hash']);
        $this->assertStringNotContainsString($secret, json_encode($settings));
    }

    public function test_the_calendar_webhook_queues_a_sync_for_a_verified_channel(): void
    {
        Bus::fake();

        $token = $this->registerChannelSecret();

        $this->postJson(route('integrations.webhooks.google-calendar'), [], [
            'X-Goog-Resource-State' => 'exists',
            'X-Goog-Channel-Token' => $token,
            'X-Goog-Channel-Id' => 'cadence-cal-'.$this->account->id.'-abc',
        ])->assertOk();

        Bus::assertDispatched(
            SyncIntegrationAccountCalendar::class,
            fn ($job) => $job->integrationAccountId === $this->account->id,
        );

        $this->assertDatabaseHas('webhook_deliveries', ['signature_verified' => true]);
    }

    public function test_the_calendar_webhook_ignores_the_initial_sync_handshake(): void
    {
        Bus::fake();

        $token = $this->registerChannelSecret();

        $this->postJson(route('integrations.webhooks.google-calendar'), [], [
            'X-Goog-Resource-State' => 'sync',
            'X-Goog-Channel-Token' => $token,
        ])->assertOk();

        Bus::assertNotDispatched(SyncIntegrationAccountCalendar::class);
    }

    /**
     * The route is public and CSRF-exempt, so a guessable token would let anyone
     * trigger sync jobs for any account. The account id alone must never be
     * enough.
     */
    public function test_the_calendar_webhook_rejects_a_guessable_account_id_token(): void
    {
        Bus::fake();

        $this->registerChannelSecret();

        foreach (['account='.$this->account->id, (string) $this->account->id, $this->account->id.'.', $this->account->id.'.wrong-secret'] as $forged) {
            $this->postJson(route('integrations.webhooks.google-calendar'), [], [
                'X-Goog-Resource-State' => 'exists',
                'X-Goog-Channel-Token' => $forged,
            ])->assertForbidden();
        }

        Bus::assertNotDispatched(SyncIntegrationAccountCalendar::class);
        $this->assertDatabaseMissing('webhook_deliveries', ['signature_verified' => true]);
    }

    public function test_the_calendar_webhook_rejects_an_account_with_no_registered_channel(): void
    {
        Bus::fake();

        // No settings written, so nothing can verify against.
        $this->postJson(route('integrations.webhooks.google-calendar'), [], [
            'X-Goog-Resource-State' => 'exists',
            'X-Goog-Channel-Token' => $this->account->id.'.anything',
        ])->assertForbidden();

        Bus::assertNotDispatched(SyncIntegrationAccountCalendar::class);
    }

    public function test_the_calendar_webhook_rejects_a_malformed_or_unknown_token(): void
    {
        Bus::fake();

        foreach (['', 'nonsense', 'abc.def', '999999.secret'] as $forged) {
            $this->postJson(route('integrations.webhooks.google-calendar'), [], [
                'X-Goog-Resource-State' => 'exists',
                'X-Goog-Channel-Token' => $forged,
            ])->assertForbidden();
        }

        Bus::assertNotDispatched(SyncIntegrationAccountCalendar::class);
    }
}
