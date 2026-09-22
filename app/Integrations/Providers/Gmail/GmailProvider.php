<?php

namespace App\Integrations\Providers\Gmail;

use App\Enums\MessageChannel;
use App\Integrations\Contracts\CalendarProvider;
use App\Integrations\Contracts\EmailProvider;
use App\Integrations\Contracts\OAuthProvider;
use App\Models\CalendarEvent;
use App\Models\CalendarSource;
use App\Models\IntegrationAccount;
use App\Models\Meeting;
use App\Models\Message;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class GmailProvider implements CalendarProvider, EmailProvider, OAuthProvider
{
    private const AUTH_ENDPOINT = 'https://accounts.google.com/o/oauth2/v2/auth';

    private const TOKEN_ENDPOINT = 'https://oauth2.googleapis.com/token';

    private const API_BASE = 'https://gmail.googleapis.com/gmail/v1/users/me';

    private const CALENDAR_API = 'https://www.googleapis.com/calendar/v3';

    /** Messages fetched per run. A first sync resumes rather than pulling all. */
    private const MAX_MESSAGES_PER_RUN = 200;

    private const INBOX_PAGE_SIZE = 50;

    /** ~40 calls a second, against a per-user budget of about 50. */
    private const INBOX_REQUEST_PAUSE_MICROSECONDS = 25000;

    public function authorizationUrl(string $state, ?string $redirectUri = null): string
    {
        return self::AUTH_ENDPOINT.'?'.http_build_query([
            'client_id' => config('integrations.google.client_id'),
            'redirect_uri' => $redirectUri ?? config('integrations.google.redirect_uri'),
            'response_type' => 'code',
            'scope' => implode(' ', config('integrations.google.scopes')),
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
        ]);
    }

    public function exchangeCode(string $code, ?string $redirectUri = null): array
    {
        $response = Http::asForm()->post(self::TOKEN_ENDPOINT, [
            'code' => $code,
            'client_id' => config('integrations.google.client_id'),
            'client_secret' => config('integrations.google.client_secret'),
            'redirect_uri' => $redirectUri ?? config('integrations.google.redirect_uri'),
            'grant_type' => 'authorization_code',
        ])->throw()->json();

        $profile = Http::withToken($response['access_token'])
            ->get('https://openidconnect.googleapis.com/v1/userinfo')
            ->throw()->json();

        return [
            'access_token' => $response['access_token'],
            'refresh_token' => $response['refresh_token'] ?? null,
            'expires_in' => $response['expires_in'] ?? null,
            'scope' => $response['scope'] ?? null,
            'external_account_id' => $profile['sub'],
            'display_name' => $profile['email'] ?? null,
        ];
    }

    public function refreshAccessToken(IntegrationAccount $account): array
    {
        if (! $account->refresh_token) {
            throw new RuntimeException('No refresh token stored.');
        }

        $response = Http::asForm()->post(self::TOKEN_ENDPOINT, [
            'client_id' => config('integrations.google.client_id'),
            'client_secret' => config('integrations.google.client_secret'),
            'refresh_token' => $account->refresh_token,
            'grant_type' => 'refresh_token',
        ])->throw()->json();

        $account->forceFill([
            'access_token' => $response['access_token'],
            'token_expires_at' => now()->addSeconds($response['expires_in'] ?? 3600),
        ])->save();

        return [
            'access_token' => $response['access_token'],
            'refresh_token' => null,
            'expires_in' => $response['expires_in'] ?? null,
            'scope' => $response['scope'] ?? null,
            'external_account_id' => $account->external_account_id,
            'display_name' => $account->display_name,
        ];
    }

    /**
     * Pull recent mail headers.
     *
     * Gmail charges quota per call — 5 units for a list, 5 for each message —
     * against a budget of roughly 250 units per user per second. The first sync
     * of a busy mailbox is the dangerous one: thirty days of mail is thousands
     * of messages, and fetching them in a tight loop exhausts the budget within
     * the first second and fails the whole run with a 403.
     *
     * So the run is bounded, paced, and resumable.
     */
    public function syncInbox(IntegrationAccount $account): int
    {
        $this->ensureValidToken($account);

        $persisted = 0;
        $fetched = 0;
        $pageToken = null;
        $cursor = $account->sync_cursor;
        $query = $cursor ? "after:{$cursor}" : 'newer_than:30d';
        $exhausted = false;

        do {
            $list = $this->gmailGet($account, self::API_BASE.'/messages', array_filter([
                'q' => $query,
                'maxResults' => self::INBOX_PAGE_SIZE,
                'pageToken' => $pageToken,
            ]));

            $stubs = collect($list['messages'] ?? [])->pluck('id')->filter();

            // One query for the whole page instead of one per message. The old
            // loop asked the database whether it had each message individually.
            $known = Message::query()
                ->where('channel', MessageChannel::Email->value)
                ->whereIn('external_id', $stubs)
                ->pluck('external_id')
                ->flip();

            foreach ($stubs as $id) {
                if ($known->has($id)) {
                    continue;
                }

                if ($fetched >= self::MAX_MESSAGES_PER_RUN) {
                    // Stop cleanly rather than burning through the quota. The
                    // cursor is deliberately left alone below so the next run
                    // asks the same question and carries on where this stopped.
                    $exhausted = true;
                    break 2;
                }

                $detail = $this->gmailGet($account, self::API_BASE."/messages/{$id}", [
                    'format' => 'metadata',
                    'metadataHeaders' => ['From', 'To', 'Subject', 'Date'],
                ]);
                $fetched++;

                Message::create([
                    'workspace_id' => $account->workspace_id,
                    'integration_account_id' => $account->id,
                    'channel' => MessageChannel::Email->value,
                    'direction' => Message::DIRECTION_INBOUND,
                    'external_id' => $detail['id'],
                    'from_address' => $this->headerValue($detail, 'From'),
                    'to_addresses' => [$this->headerValue($detail, 'To')],
                    'subject' => $this->headerValue($detail, 'Subject'),
                    'body_text' => $detail['snippet'] ?? null,
                    'status' => 'received',
                    // The message's own Date header, not the moment we happened
                    // to read it, which put every synced mail at "just now".
                    'sent_at' => $this->parseHeaderDate($detail) ?? now(),
                ]);

                $persisted++;
            }

            $pageToken = $list['nextPageToken'] ?? null;
        } while ($pageToken);

        $account->forceFill([
            'last_synced_at' => now(),
            // Only move the cursor once the window is genuinely drained.
            // Advancing it after a capped run would skip everything this run did
            // not reach, and that mail would never be fetched.
            'sync_cursor' => $exhausted ? $account->sync_cursor : now()->format('Y/m/d'),
            'last_error' => null,
        ])->save();

        return $persisted;
    }

    /**
     * A Gmail GET that respects the quota.
     *
     * Paced to stay inside the per-user budget, and retried with backoff when
     * Google says we are going too fast — a rate limit is a "wait" answer, not
     * a failure, and treating it as fatal loses the whole sync.
     *
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    protected function gmailGet(IntegrationAccount $account, string $url, array $query = []): array
    {
        // ~5 quota units a call against ~250 per user per second. This keeps a
        // single account well under its own share without needing a shared
        // token bucket across workers.
        usleep(self::INBOX_REQUEST_PAUSE_MICROSECONDS);

        return Http::withToken($account->access_token)
            ->retry(
                times: 4,
                sleepMilliseconds: fn (int $attempt) => min(1000 * (2 ** ($attempt - 1)), 8000),
                when: fn (Throwable $e) => $e instanceof ConnectionException
                    || ($e instanceof RequestException && $this->isRateLimited($e)),
                throw: true,
            )
            ->get($url, $query)
            ->throw()
            ->json();
    }

    /**
     * Whether Google is asking us to slow down rather than refusing outright.
     *
     * Gmail reports throttling as 429, and confusingly also as 403 with a
     * rate-limit reason — a plain 403 means no permission and must not be
     * retried, so the reason has to be read rather than the status alone.
     */
    protected function isRateLimited(RequestException $e): bool
    {
        if ($e->response->status() === 429) {
            return true;
        }

        if ($e->response->status() !== 403) {
            return false;
        }

        $reason = $e->response->json('error.errors.0.reason', '');
        $message = (string) $e->response->json('error.message', '');

        return in_array($reason, ['rateLimitExceeded', 'userRateLimitExceeded'], true)
            || str_contains($message, 'Quota exceeded');
    }

    /**
     * The Date header as a timestamp, or null if it is missing or unparseable —
     * senders do put nonsense in there.
     *
     * @param  array<string, mixed>  $detail
     */
    protected function parseHeaderDate(array $detail): ?CarbonImmutable
    {
        $raw = $this->headerValue($detail, 'Date');

        if (! $raw) {
            return null;
        }

        try {
            // Normalised to UTC: the column carries no offset, so storing the
            // sender's wall clock would place a 09:30+0200 mail at 09:30 UTC
            // and shift it two hours everywhere it is read back.
            return CarbonImmutable::parse($raw)->utc();
        } catch (Throwable) {
            return null;
        }
    }

    public function send(IntegrationAccount $account, array $payload): Message
    {
        $this->ensureValidToken($account);

        $raw = $this->buildRawEmail($payload);

        $response = Http::withToken($account->access_token)
            ->post(self::API_BASE.'/messages/send', [
                'raw' => rtrim(strtr(base64_encode($raw), '+/', '-_'), '='),
            ])
            ->throw()->json();

        return Message::create([
            'workspace_id' => $account->workspace_id,
            'integration_account_id' => $account->id,
            'channel' => MessageChannel::Email->value,
            'direction' => Message::DIRECTION_OUTBOUND,
            'external_id' => $response['id'],
            'from_address' => $payload['from'] ?? $account->display_name,
            'to_addresses' => Arr::wrap($payload['to']),
            'subject' => $payload['subject'] ?? null,
            'body_text' => $payload['body_text'] ?? null,
            'body_html' => $payload['body_html'] ?? null,
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }

    public function watchInbox(IntegrationAccount $account): void
    {
        $topic = config('integrations.google.pubsub_topic');
        if (! $topic) {
            return;
        }

        $this->ensureValidToken($account);

        Http::withToken($account->access_token)
            ->post(self::API_BASE.'/watch', [
                'topicName' => $topic,
                'labelIds' => ['INBOX'],
            ])
            ->throw();
    }

    /**
     * Refresh the account's CalendarList.
     *
     * Cadence used to read only the calendar literally named `primary`, so a
     * shared team calendar, a partner's calendar or a second personal one simply
     * did not exist as far as the app was concerned.
     */
    public function syncCalendarList(IntegrationAccount $account): int
    {
        $this->ensureValidToken($account);

        $seen = [];
        $pageToken = null;

        do {
            $response = Http::withToken($account->access_token)
                ->get(self::CALENDAR_API.'/users/me/calendarList', array_filter([
                    // Hidden calendars are listed too, so the user can turn one
                    // back on from Cadence without going to Google first.
                    'showHidden' => 'true',
                    'maxResults' => 250,
                    'pageToken' => $pageToken,
                ]))
                ->throw()
                ->json();

            foreach (($response['items'] ?? []) as $entry) {
                if (! empty($entry['deleted'])) {
                    CalendarSource::query()
                        ->where('integration_account_id', $account->id)
                        ->where('external_id', $entry['id'])
                        ->delete();

                    continue;
                }

                $source = CalendarSource::query()->firstOrNew([
                    'integration_account_id' => $account->id,
                    'external_id' => $entry['id'],
                ]);

                $source->fill([
                    'workspace_id' => $account->workspace_id,
                    'name' => $entry['summaryOverride'] ?? $entry['summary'] ?? $entry['id'],
                    'description' => $entry['description'] ?? null,
                    'timezone' => $entry['timeZone'] ?? null,
                    'color' => $entry['backgroundColor'] ?? null,
                    'access_role' => $entry['accessRole'] ?? null,
                    'is_primary' => (bool) ($entry['primary'] ?? false),
                ]);

                // Which calendars to pull is the user's call, so their choice is
                // only seeded from Google's own "selected" flag the first time a
                // calendar is seen, never re-imposed on later refreshes.
                if (! $source->exists) {
                    $source->is_selected = (bool) ($entry['selected'] ?? $entry['primary'] ?? false);
                }

                $source->save();
                $seen[] = $source->id;
            }

            $pageToken = $response['nextPageToken'] ?? null;
        } while ($pageToken);

        // Calendars the account no longer has access to. Their events go with
        // them, since a row nobody can refresh only gets staler.
        CalendarSource::query()
            ->where('integration_account_id', $account->id)
            ->when($seen, fn ($q) => $q->whereNotIn('id', $seen))
            ->delete();

        $this->ensureWriteTarget($account);

        return count($seen);
    }

    public function syncEvents(IntegrationAccount $account): int
    {
        $this->ensureValidToken($account);

        // A first sync has no calendar list yet; without this the account would
        // report zero events and never explain why.
        if (! $account->calendarSources()->exists()) {
            $this->syncCalendarList($account);
        }

        $persisted = 0;

        foreach ($account->calendarSources()->selected()->get() as $source) {
            // One calendar failing — revoked access, a bad token, a calendar
            // deleted mid-sync — must not stop the others being pulled.
            try {
                $persisted += $this->syncCalendarEvents($account, $source);
            } catch (RequestException $e) {
                $source->forceFill([
                    'last_error' => Str::limit($e->getMessage(), 1000),
                ])->save();
            }
        }

        $account->forceFill([
            'last_synced_at' => now(),
            'last_error' => null,
        ])->save();

        return $persisted;
    }

    /**
     * Pull one calendar, recovering from an expired sync token.
     */
    protected function syncCalendarEvents(IntegrationAccount $account, CalendarSource $source): int
    {
        try {
            return $this->pullEvents($account, $source, $source->sync_cursor);
        } catch (RequestException $e) {
            $status = $e->response->status();

            // 410 GONE means the sync token aged out; 400 means Google rejected it
            // as incompatible with the request. Either way the only recovery is a
            // full re-sync, so the cursor is dropped and the pull retried once.
            // Without this a calendar stops updating permanently.
            if (! $source->sync_cursor || ! in_array($status, [400, 410], true)) {
                throw $e;
            }

            $source->forceFill(['sync_cursor' => null])->save();

            return $this->pullEvents($account, $source, null);
        }
    }

    /**
     * Read one page-set of events from one calendar into `calendar_events`.
     *
     * `singleEvents=true` is sent on every request, incremental included, because
     * Google requires the parameters behind a sync token to stay identical. That
     * also means recurring series arrive already expanded into instances, each
     * carrying `recurringEventId` so they can be grouped back together.
     */
    protected function pullEvents(IntegrationAccount $account, CalendarSource $source, ?string $syncToken): int
    {
        $persisted = 0;
        $pageToken = null;
        $nextSyncToken = null;

        $params = [
            'singleEvents' => 'true',
            'maxResults' => 250,
        ];

        if ($syncToken) {
            // timeMin cannot be combined with a sync token.
            $params['syncToken'] = $syncToken;
        } else {
            $params['timeMin'] = now()->subMonth()->toIso8601String();
        }

        do {
            if ($pageToken) {
                $params['pageToken'] = $pageToken;
            }

            $response = Http::withToken($account->access_token)
                ->get($this->calendarBase($source->external_id).'/events', $params)
                ->throw()
                ->json();

            foreach (($response['items'] ?? []) as $event) {
                if (($event['status'] ?? '') === 'cancelled') {
                    CalendarEvent::query()
                        ->where('integration_account_id', $account->id)
                        ->where('calendar_source_id', $source->id)
                        ->where('external_id', $event['id'])
                        ->delete();

                    continue;
                }

                // meeting_id is deliberately absent from the update payload: it is
                // the link Cadence owns, and must survive a sync rewriting the row.
                CalendarEvent::query()->updateOrCreate(
                    [
                        'integration_account_id' => $account->id,
                        'calendar_source_id' => $source->id,
                        'external_id' => $event['id'],
                    ],
                    $this->eventAttributes($account, $event, $source),
                );

                $persisted++;
            }

            $pageToken = $response['nextPageToken'] ?? null;
            $nextSyncToken = $response['nextSyncToken'] ?? $nextSyncToken;
        } while ($pageToken);

        $source->forceFill([
            'last_synced_at' => now(),
            'sync_cursor' => $nextSyncToken ?: $source->sync_cursor,
            'last_error' => null,
        ])->save();

        return $persisted;
    }

    /**
     * Map a Google event onto the columns of `calendar_events`.
     *
     * @param  array<string, mixed>  $event
     * @return array<string, mixed>
     */
    protected function eventAttributes(IntegrationAccount $account, array $event, ?CalendarSource $source = null): array
    {
        $start = $event['start'] ?? [];
        $end = $event['end'] ?? [];

        $attendees = collect($event['attendees'] ?? [])
            ->filter(fn ($attendee) => ! empty($attendee['email']))
            ->values();

        return [
            'workspace_id' => $account->workspace_id,
            'calendar_source_id' => $source?->id,
            'etag' => $event['etag'] ?? null,
            'title' => $event['summary'] ?? '(no title)',
            'description' => $event['description'] ?? null,
            'location' => $event['location'] ?? null,
            'all_day' => ! isset($start['dateTime']) && isset($start['date']),
            'recurrence' => $event['recurrence'] ?? null,
            'recurring_event_id' => $event['recurringEventId'] ?? null,
            'organizer_email' => $event['organizer']['email'] ?? null,
            // Google marks the account's own attendee row with `self`, which is
            // the only reliable way to tell which RSVP is ours.
            'response_status' => $attendees->firstWhere('self', true)['responseStatus'] ?? null,
            'html_link' => $event['htmlLink'] ?? null,
            'conference_url' => $event['hangoutLink'] ?? null,
            'starts_at' => $this->parseEventTime($start),
            'ends_at' => $this->parseEventTime($end),
            'attendees' => $attendees
                ->map(fn ($attendee) => [
                    'email' => $attendee['email'],
                    'name' => $attendee['displayName'] ?? null,
                    'response_status' => $attendee['responseStatus'] ?? 'needsAction',
                ])
                ->all(),
            'sync_status' => 'synced',
        ];
    }

    public function createEvent(IntegrationAccount $account, Meeting $meeting): CalendarEvent
    {
        $this->ensureValidToken($account);

        $target = $this->writeTarget($account);

        $response = Http::withToken($account->access_token)
            ->post(
                $this->calendarBase($target?->external_id ?? 'primary')
                    .'/events?'.http_build_query($this->writeQuery($meeting)),
                $this->meetingPayload($meeting),
            )
            ->throw()
            ->json();

        $this->captureConferenceUrl($meeting, $response);

        return CalendarEvent::query()->updateOrCreate(
            [
                'integration_account_id' => $account->id,
                'calendar_source_id' => $target?->id,
                'external_id' => $response['id'],
            ],
            $this->eventAttributes($account, $response, $target) + ['meeting_id' => $meeting->id],
        );
    }

    public function updateEvent(IntegrationAccount $account, CalendarEvent $event, Meeting $meeting): CalendarEvent
    {
        $this->ensureValidToken($account);

        // Edited on whichever calendar it was created on, not on whatever the
        // write target happens to be today — moving the target must not orphan
        // events already out there.
        $calendarId = $event->calendarSource?->external_id
            ?? $this->writeTarget($account)?->external_id
            ?? 'primary';

        $response = Http::withToken($account->access_token)
            ->patch(
                $this->calendarBase($calendarId).'/events/'.rawurlencode($event->external_id)
                    .'?'.http_build_query($this->writeQuery($meeting)),
                $this->meetingPayload($meeting),
            )
            ->throw()
            ->json();

        $this->captureConferenceUrl($meeting, $response);

        $event->forceFill(
            $this->eventAttributes($account, $response, $event->calendarSource)
                + ['meeting_id' => $meeting->id]
        )->save();

        return $event;
    }

    public function deleteEvent(IntegrationAccount $account, CalendarEvent $event): void
    {
        $this->ensureValidToken($account);

        $calendarId = $event->calendarSource?->external_id ?? 'primary';

        $response = Http::withToken($account->access_token)
            ->delete($this->calendarBase($calendarId).'/events/'.rawurlencode($event->external_id), [
                // Tell the attendees the meeting is off.
                'sendUpdates' => 'all',
            ]);

        // Already gone upstream is the outcome we were after.
        if (! $response->successful() && ! in_array($response->status(), [404, 410], true)) {
            $response->throw();
        }

        $event->delete();
    }

    public function watchCalendar(IntegrationAccount $account, CalendarSource $source): void
    {
        // Push needs a publicly reachable HTTPS callback on a domain Google has
        // verified. Without one configured we fall back to scheduled polling,
        // which is why this returns quietly instead of failing the sync.
        if (! config('integrations.google.calendar_push_enabled')) {
            return;
        }

        $this->ensureValidToken($account);

        $channelId = 'cadence-cal-'.$source->id.'-'.Str::random(16);

        // The channel token is the only thing authenticating an inbound
        // notification, so it has to be a secret rather than something guessable.
        // The source id is carried alongside it purely to find the row; the
        // random half is what authorises the request. Only the hash is kept, so a
        // leaked settings blob cannot be replayed against the webhook.
        $secret = Str::random(48);

        $response = Http::withToken($account->access_token)
            ->post($this->calendarBase($source->external_id).'/events/watch', [
                'id' => $channelId,
                'type' => 'web_hook',
                'address' => config('integrations.google.calendar_webhook_url')
                    ?: route('integrations.webhooks.google-calendar'),
                // Echoed back on every notification.
                'token' => $source->id.'.'.$secret,
            ])
            ->throw()
            ->json();

        $source->forceFill([
            'watch_channel_id' => $channelId,
            'watch_channel_token_hash' => hash('sha256', $secret),
            'watch_resource_id' => $response['resourceId'] ?? null,
            'watch_expires_at' => isset($response['expiration'])
                ? CarbonImmutable::createFromTimestampMs((int) $response['expiration'])
                : null,
        ])->save();
    }

    /** Per-calendar API base. Calendar ids are email-like, so they need escaping. */
    protected function calendarBase(string $calendarId): string
    {
        return self::CALENDAR_API.'/calendars/'.rawurlencode($calendarId);
    }

    /**
     * The calendar meetings are written to, discovering the list first if this
     * account has never had one.
     */
    protected function writeTarget(IntegrationAccount $account): ?CalendarSource
    {
        if (! $account->calendarSources()->exists()) {
            $this->syncCalendarList($account);
            // The discovery just wrote rows the account may already hold a
            // stale (empty) copy of; dropping it forces a re-read.
            $account->unsetRelation('calendarSources');
        }

        return $account->writeTargetCalendar();
    }

    /**
     * Make sure exactly one calendar is marked as the write target, so a push
     * never has to guess. Left alone once the user has chosen one.
     */
    protected function ensureWriteTarget(IntegrationAccount $account): void
    {
        if ($account->calendarSources()->where('is_write_target', true)->exists()) {
            return;
        }

        $default = $account->calendarSources()->where('is_primary', true)->first()
            ?? $account->calendarSources()
                ->whereIn('access_role', CalendarSource::WRITABLE_ROLES)
                ->first();

        // Also read back, or a meeting pushed here would never return on the
        // next sync and the calendar page would look like it had been lost.
        $default?->forceFill(['is_write_target' => true, 'is_selected' => true])->save();
    }

    /**
     * Query parameters for a write. `sendUpdates=all` is what makes Google email
     * the attendees — without it invitations are created silently and nobody
     * outside Cadence ever hears about the meeting.
     *
     * @return array<string, string|int>
     */
    protected function writeQuery(Meeting $meeting): array
    {
        $query = ['sendUpdates' => 'all'];

        if ($meeting->conference_requested) {
            $query['conferenceDataVersion'] = 1;
        }

        return $query;
    }

    /**
     * Persist a Meet link Google minted for us.
     *
     * Saved quietly on purpose: MeetingObserver watches `meeting_url`, so a normal
     * save here would dispatch another push job and loop.
     *
     * @param  array<string, mixed>  $response
     */
    protected function captureConferenceUrl(Meeting $meeting, array $response): void
    {
        $link = $response['hangoutLink']
            ?? $response['conferenceData']['entryPoints'][0]['uri']
            ?? null;

        if ($link && $meeting->meeting_url !== $link) {
            $meeting->meeting_url = $link;
            $meeting->saveQuietly();
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function meetingPayload(Meeting $meeting): array
    {
        $attendees = $meeting->attendees()
            ->pluck('email')
            ->filter()
            ->map(fn ($email) => ['email' => $email])
            ->values()
            ->all();

        $payload = [
            'summary' => $meeting->title,
            'description' => $meeting->description,
            'location' => $meeting->location ?: $meeting->meeting_url,
            'start' => ['dateTime' => $meeting->starts_at->toIso8601String()],
            'end' => ['dateTime' => $meeting->ends_at->toIso8601String()],
            'attendees' => $attendees,
        ];

        // A repeating meeting is one event carrying an RRULE, not one event per
        // occurrence — otherwise the series is redrawn across every calendar.
        if ($rules = $meeting->recurrenceRules()) {
            $payload['recurrence'] = $rules;
        }

        if ($meeting->conference_requested && ! $meeting->meeting_url) {
            $payload['conferenceData'] = [
                'createRequest' => [
                    'requestId' => 'cadence-meeting-'.$meeting->id,
                    'conferenceSolutionKey' => ['type' => 'hangoutsMeet'],
                ],
            ];
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $time
     */
    protected function parseEventTime(array $time): ?CarbonImmutable
    {
        if (! empty($time['dateTime'])) {
            return CarbonImmutable::parse($time['dateTime']);
        }
        if (! empty($time['date'])) {
            return CarbonImmutable::parse($time['date']);
        }

        return null;
    }

    protected function ensureValidToken(IntegrationAccount $account): void
    {
        if ($account->tokenIsExpired()) {
            $this->refreshAccessToken($account);
            $account->refresh();
        }
    }

    protected function headerValue(array $detail, string $name): ?string
    {
        $headers = $detail['payload']['headers'] ?? [];
        foreach ($headers as $header) {
            if (strcasecmp($header['name'], $name) === 0) {
                return $header['value'];
            }
        }

        return null;
    }

    protected function buildRawEmail(array $payload): string
    {
        $to = implode(', ', Arr::wrap($payload['to']));
        $subject = $payload['subject'] ?? '';
        $bodyHtml = $payload['body_html'] ?? null;
        $bodyText = $payload['body_text'] ?? '';

        if ($bodyHtml) {
            return "To: {$to}\r\nSubject: {$subject}\r\nContent-Type: text/html; charset=UTF-8\r\n\r\n{$bodyHtml}";
        }

        return "To: {$to}\r\nSubject: {$subject}\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n{$bodyText}";
    }
}
