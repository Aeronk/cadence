<?php

namespace App\Integrations\Providers\Microsoft;

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
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class MicrosoftProvider implements CalendarProvider, EmailProvider, OAuthProvider
{
    private const GRAPH_BASE = 'https://graph.microsoft.com/v1.0';

    public function authorizationUrl(string $state, ?string $redirectUri = null): string
    {
        $tenant = config('integrations.microsoft.tenant_id');

        return "https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/authorize?".http_build_query([
            'client_id' => config('integrations.microsoft.client_id'),
            'response_type' => 'code',
            'redirect_uri' => $redirectUri ?? config('integrations.microsoft.redirect_uri'),
            'response_mode' => 'query',
            'scope' => implode(' ', config('integrations.microsoft.scopes')),
            'state' => $state,
        ]);
    }

    public function exchangeCode(string $code, ?string $redirectUri = null): array
    {
        $response = $this->tokenRequest([
            'code' => $code,
            'redirect_uri' => $redirectUri ?? config('integrations.microsoft.redirect_uri'),
            'grant_type' => 'authorization_code',
        ]);

        $profile = Http::withToken($response['access_token'])
            ->get(self::GRAPH_BASE.'/me')
            ->throw()->json();

        return [
            'access_token' => $response['access_token'],
            'refresh_token' => $response['refresh_token'] ?? null,
            'expires_in' => $response['expires_in'] ?? null,
            'scope' => $response['scope'] ?? null,
            'external_account_id' => $profile['id'],
            'display_name' => $profile['userPrincipalName'] ?? $profile['mail'] ?? null,
        ];
    }

    public function refreshAccessToken(IntegrationAccount $account): array
    {
        if (! $account->refresh_token) {
            throw new RuntimeException('No refresh token stored.');
        }

        $response = $this->tokenRequest([
            'refresh_token' => $account->refresh_token,
            'grant_type' => 'refresh_token',
        ]);

        $account->forceFill([
            'access_token' => $response['access_token'],
            'refresh_token' => $response['refresh_token'] ?? $account->refresh_token,
            'token_expires_at' => now()->addSeconds($response['expires_in'] ?? 3600),
        ])->save();

        return [
            'access_token' => $response['access_token'],
            'refresh_token' => $response['refresh_token'] ?? null,
            'expires_in' => $response['expires_in'] ?? null,
            'scope' => $response['scope'] ?? null,
            'external_account_id' => $account->external_account_id,
            'display_name' => $account->display_name,
        ];
    }

    public function syncInbox(IntegrationAccount $account): int
    {
        $this->ensureValidToken($account);

        $persisted = 0;
        $url = $account->sync_cursor
            ?: self::GRAPH_BASE.'/me/mailFolders/Inbox/messages/delta?$select=id,from,toRecipients,subject,bodyPreview,receivedDateTime&$top=50';

        do {
            $response = Http::withToken($account->access_token)->get($url)->throw()->json();

            foreach (($response['value'] ?? []) as $msg) {
                if (! isset($msg['id'])) {
                    continue;
                }

                if (Message::query()
                    ->where('channel', MessageChannel::Email->value)
                    ->where('external_id', $msg['id'])
                    ->exists()) {
                    continue;
                }

                Message::create([
                    'workspace_id' => $account->workspace_id,
                    'integration_account_id' => $account->id,
                    'channel' => MessageChannel::Email->value,
                    'direction' => Message::DIRECTION_INBOUND,
                    'external_id' => $msg['id'],
                    'from_address' => $msg['from']['emailAddress']['address'] ?? null,
                    'to_addresses' => collect($msg['toRecipients'] ?? [])
                        ->pluck('emailAddress.address')->filter()->values()->all(),
                    'subject' => $msg['subject'] ?? null,
                    'body_text' => $msg['bodyPreview'] ?? null,
                    'status' => 'received',
                    'sent_at' => isset($msg['receivedDateTime']) ? CarbonImmutable::parse($msg['receivedDateTime']) : now(),
                ]);

                $persisted++;
            }

            $url = $response['@odata.nextLink'] ?? null;
            $deltaLink = $response['@odata.deltaLink'] ?? null;
        } while ($url);

        $account->forceFill([
            'last_synced_at' => now(),
            'sync_cursor' => $deltaLink ?? $account->sync_cursor,
            'last_error' => null,
        ])->save();

        return $persisted;
    }

    public function send(IntegrationAccount $account, array $payload): Message
    {
        $this->ensureValidToken($account);

        $to = collect(Arr::wrap($payload['to']))
            ->map(fn ($addr) => ['emailAddress' => ['address' => $addr]])
            ->all();

        Http::withToken($account->access_token)
            ->post(self::GRAPH_BASE.'/me/sendMail', [
                'message' => [
                    'subject' => $payload['subject'] ?? '',
                    'body' => [
                        'contentType' => isset($payload['body_html']) ? 'HTML' : 'Text',
                        'content' => $payload['body_html'] ?? ($payload['body_text'] ?? ''),
                    ],
                    'toRecipients' => $to,
                ],
                'saveToSentItems' => true,
            ])
            ->throw();

        return Message::create([
            'workspace_id' => $account->workspace_id,
            'integration_account_id' => $account->id,
            'channel' => MessageChannel::Email->value,
            'direction' => Message::DIRECTION_OUTBOUND,
            'external_id' => null,
            'from_address' => $account->display_name,
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
        $this->ensureValidToken($account);

        Http::withToken($account->access_token)
            ->post(self::GRAPH_BASE.'/subscriptions', [
                'changeType' => 'created',
                'notificationUrl' => config('integrations.microsoft.webhook_url'),
                'resource' => '/me/mailFolders(\'Inbox\')/messages',
                'expirationDateTime' => now()->addDays(2)->toIso8601String(),
                'clientState' => $this->registerSubscriptionSecret($account, 'graph_inbox_token_hash'),
            ])
            ->throw();
    }

    /**
     * Refresh the account's calendars from Graph.
     *
     * Graph has no CalendarList as such; `/me/calendars` is the equivalent, and
     * `canEdit` stands in for Google's access role.
     */
    public function syncCalendarList(IntegrationAccount $account): int
    {
        $this->ensureValidToken($account);

        $seen = [];
        $url = self::GRAPH_BASE.'/me/calendars?$select=id,name,color,hexColor,isDefaultCalendar,canEdit,owner&$top=100';

        do {
            $response = Http::withToken($account->access_token)->get($url)->throw()->json();

            foreach (($response['value'] ?? []) as $entry) {
                if (empty($entry['id'])) {
                    continue;
                }

                $source = CalendarSource::query()->firstOrNew([
                    'integration_account_id' => $account->id,
                    'external_id' => $entry['id'],
                ]);

                $source->fill([
                    'workspace_id' => $account->workspace_id,
                    'name' => $entry['name'] ?? $entry['id'],
                    'color' => $entry['hexColor'] ?: null,
                    // Graph reports a boolean rather than a role, so it is mapped
                    // onto the same vocabulary the rest of the app already uses.
                    'access_role' => ($entry['canEdit'] ?? true) ? 'writer' : 'reader',
                    'is_primary' => (bool) ($entry['isDefaultCalendar'] ?? false),
                ]);

                // The user's choice of what to pull is only seeded on first
                // sight, never re-imposed by a later refresh.
                if (! $source->exists) {
                    $source->is_selected = (bool) ($entry['isDefaultCalendar'] ?? false);
                }

                $source->save();
                $seen[] = $source->id;
            }

            $url = $response['@odata.nextLink'] ?? null;
        } while ($url);

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

        if (! $account->calendarSources()->exists()) {
            $this->syncCalendarList($account);
        }

        $persisted = 0;

        foreach ($account->calendarSources()->selected()->get() as $source) {
            // One calendar failing must not stop the others being pulled.
            try {
                $persisted += $this->pullEvents($account, $source);
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
     * Walk one calendar's delta feed. The delta link is stored per calendar —
     * a link from one calendar means nothing to another.
     */
    protected function pullEvents(IntegrationAccount $account, CalendarSource $source): int
    {
        $persisted = 0;
        $url = $source->sync_cursor ?: $this->deltaUrl($source);
        $deltaLink = null;

        do {
            $response = Http::withToken($account->access_token)->get($url)->throw()->json();

            foreach (($response['value'] ?? []) as $event) {
                if (! isset($event['id'])) {
                    continue;
                }

                if (! empty($event['isCancelled']) || isset($event['@removed'])) {
                    CalendarEvent::query()
                        ->where('integration_account_id', $account->id)
                        ->where('calendar_source_id', $source->id)
                        ->where('external_id', $event['id'])
                        ->delete();

                    continue;
                }

                // meeting_id is deliberately absent: it is the link Cadence owns,
                // and must survive a sync rewriting the row.
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

            $url = $response['@odata.nextLink'] ?? null;
            $deltaLink = $response['@odata.deltaLink'] ?? $deltaLink;
        } while ($url);

        $source->forceFill([
            'last_synced_at' => now(),
            'sync_cursor' => $deltaLink ?: $source->sync_cursor,
            'last_error' => null,
        ])->save();

        return $persisted;
    }

    protected function deltaUrl(CalendarSource $source): string
    {
        return self::GRAPH_BASE.'/me/calendars/'.rawurlencode($source->external_id)
            .'/events/delta?$select=id,subject,bodyPreview,location,start,end,attendees,isCancelled,isAllDay,recurrence,seriesMasterId,organizer,webLink,onlineMeeting,responseStatus';
    }

    /**
     * Map a Graph event onto the columns of `calendar_events`.
     *
     * @param  array<string, mixed>  $event
     * @return array<string, mixed>
     */
    protected function eventAttributes(IntegrationAccount $account, array $event, ?CalendarSource $source = null): array
    {
        return [
            'workspace_id' => $account->workspace_id,
            'calendar_source_id' => $source?->id,
            'title' => $event['subject'] ?? '(no title)',
            'description' => $event['bodyPreview'] ?? null,
            'location' => $event['location']['displayName'] ?? null,
            'all_day' => (bool) ($event['isAllDay'] ?? false),
            // Graph describes recurrence as an object rather than RRULE strings;
            // stored as-is, since it is only read back to flag a series.
            'recurrence' => isset($event['recurrence']) ? [$event['recurrence']] : null,
            'recurring_event_id' => $event['seriesMasterId'] ?? null,
            'organizer_email' => $event['organizer']['emailAddress']['address'] ?? null,
            // Graph reports the account's own RSVP on the event itself rather
            // than hiding it among the attendees.
            'response_status' => $event['responseStatus']['response'] ?? null,
            'html_link' => $event['webLink'] ?? null,
            'conference_url' => $event['onlineMeeting']['joinUrl'] ?? null,
            'starts_at' => isset($event['start']['dateTime'])
                ? CarbonImmutable::parse($event['start']['dateTime'])
                : null,
            'ends_at' => isset($event['end']['dateTime'])
                ? CarbonImmutable::parse($event['end']['dateTime'])
                : null,
            'attendees' => collect($event['attendees'] ?? [])
                ->filter(fn ($attendee) => ! empty($attendee['emailAddress']['address']))
                ->map(fn ($attendee) => [
                    'email' => $attendee['emailAddress']['address'],
                    'name' => $attendee['emailAddress']['name'] ?? null,
                    'response_status' => $attendee['status']['response'] ?? 'none',
                ])
                ->values()
                ->all(),
            'sync_status' => 'synced',
        ];
    }

    public function createEvent(IntegrationAccount $account, Meeting $meeting): CalendarEvent
    {
        $this->ensureValidToken($account);

        $target = $this->writeTarget($account);

        $response = Http::withToken($account->access_token)
            ->post($this->eventsEndpoint($target), $this->meetingPayload($meeting))
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

        // Graph addresses an event by id under /me/events regardless of which
        // calendar holds it, so no calendar needs naming here.
        $response = Http::withToken($account->access_token)
            ->patch(self::GRAPH_BASE.'/me/events/'.rawurlencode($event->external_id), $this->meetingPayload($meeting))
            ->throw()
            ->json();

        $this->captureConferenceUrl($meeting, $response);

        $event->forceFill(
            $this->eventAttributes($account, $response, $event->calendarSource)
                + ['meeting_id' => $meeting->id]
        )->save();

        return $event;
    }

    /**
     * Persist a Teams link Graph minted for us.
     *
     * Saved quietly on purpose: MeetingObserver watches `meeting_url`, so a normal
     * save here would dispatch another push job and loop.
     *
     * @param  array<string, mixed>  $response
     */
    protected function captureConferenceUrl(Meeting $meeting, array $response): void
    {
        $link = $response['onlineMeeting']['joinUrl'] ?? null;

        if ($link && $meeting->meeting_url !== $link) {
            $meeting->meeting_url = $link;
            $meeting->saveQuietly();
        }
    }

    public function deleteEvent(IntegrationAccount $account, CalendarEvent $event): void
    {
        $this->ensureValidToken($account);

        $response = Http::withToken($account->access_token)
            ->delete(self::GRAPH_BASE.'/me/events/'.rawurlencode($event->external_id));

        // Already gone upstream is the outcome we were after.
        if (! $response->successful() && ! in_array($response->status(), [404, 410], true)) {
            $response->throw();
        }

        $event->delete();
    }

    public function watchCalendar(IntegrationAccount $account, CalendarSource $source): void
    {
        $this->ensureValidToken($account);

        // Graph caps a calendar subscription at roughly three days, so this is
        // re-run on a schedule rather than once at connect time.
        $expiresAt = now()->addDays(2);

        $response = Http::withToken($account->access_token)
            ->post(self::GRAPH_BASE.'/subscriptions', [
                'changeType' => 'created,updated,deleted',
                'notificationUrl' => config('integrations.microsoft.webhook_url'),
                'resource' => "/me/calendars/{$source->external_id}/events",
                'expirationDateTime' => $expiresAt->toIso8601String(),
                'clientState' => $this->registerCalendarSecret($source),
            ])
            ->throw()
            ->json();

        $source->forceFill([
            'watch_channel_id' => $response['id'] ?? null,
            'watch_resource_id' => $response['resource'] ?? null,
            'watch_expires_at' => isset($response['expirationDateTime'])
                ? CarbonImmutable::parse($response['expirationDateTime'])
                : $expiresAt,
        ])->save();
    }

    protected function eventsEndpoint(?CalendarSource $target): string
    {
        return $target
            ? self::GRAPH_BASE.'/me/calendars/'.rawurlencode($target->external_id).'/events'
            : self::GRAPH_BASE.'/me/events';
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
     * Mint the clientState for a calendar subscription and keep only its hash,
     * so a leaked row cannot be replayed against the webhook. Shaped
     * `<sourceId>.<secret>`: the id selects the row, the secret authorises.
     */
    protected function registerCalendarSecret(CalendarSource $source): string
    {
        $secret = Str::random(48);

        $source->forceFill(['watch_channel_token_hash' => hash('sha256', $secret)])->save();

        return $source->id.'.'.$secret;
    }

    protected function meetingPayload(Meeting $meeting): array
    {
        $attendees = $meeting->attendees()
            ->pluck('email')
            ->filter()
            ->map(fn ($email) => ['emailAddress' => ['address' => $email], 'type' => 'required'])
            ->values()
            ->all();

        $payload = [
            'subject' => $meeting->title,
            'body' => [
                'contentType' => 'HTML',
                'content' => $meeting->description ?? '',
            ],
            'location' => ['displayName' => $meeting->location ?: ($meeting->meeting_url ?? '')],
            'start' => ['dateTime' => $meeting->starts_at->toIso8601String(), 'timeZone' => 'UTC'],
            'end' => ['dateTime' => $meeting->ends_at->toIso8601String(), 'timeZone' => 'UTC'],
            'attendees' => $attendees,
        ];

        // A repeating meeting is one recurring event, not one event per
        // occurrence — otherwise the series is redrawn across every calendar.
        if ($pattern = $this->recurrencePattern($meeting)) {
            $payload['recurrence'] = $pattern;
        }

        if ($meeting->conference_requested && ! $meeting->meeting_url) {
            $payload['isOnlineMeeting'] = true;
            $payload['onlineMeetingProvider'] = 'teamsForBusiness';
        }

        return $payload;
    }

    /**
     * Graph wants a pattern/range object rather than an RRULE string.
     *
     * @return array<string, mixed>|null
     */
    protected function recurrencePattern(Meeting $meeting): ?array
    {
        if (! $meeting->isRecurring()) {
            return null;
        }

        $pattern = match ($meeting->recurrence_rule) {
            'daily' => ['type' => 'daily', 'interval' => 1],
            'weekly' => [
                'type' => 'weekly',
                'interval' => 1,
                'daysOfWeek' => [strtolower($meeting->starts_at->format('l'))],
            ],
            'monthly' => [
                'type' => 'absoluteMonthly',
                'interval' => 1,
                'dayOfMonth' => (int) $meeting->starts_at->format('j'),
            ],
            'yearly' => [
                'type' => 'absoluteYearly',
                'interval' => 1,
                'dayOfMonth' => (int) $meeting->starts_at->format('j'),
                'month' => (int) $meeting->starts_at->format('n'),
            ],
            default => null,
        };

        if (! $pattern) {
            return null;
        }

        $range = $meeting->recurrence_ends_on
            ? [
                'type' => 'endDate',
                'startDate' => $meeting->starts_at->toDateString(),
                'endDate' => $meeting->recurrence_ends_on->toDateString(),
            ]
            : [
                'type' => 'noEnd',
                'startDate' => $meeting->starts_at->toDateString(),
            ];

        return ['pattern' => $pattern, 'range' => $range];
    }

    /**
     * Mint the clientState for a Graph subscription and store only its hash.
     *
     * clientState is the only thing authenticating an inbound notification, so it
     * has to be a secret. The account id is carried alongside purely to find the
     * row — it is sequential and guessable, so it is never sufficient on its own.
     *
     * Only the inbox subscription lives on the account. Calendar subscriptions
     * are per calendar and keep their secret on the calendar_sources row, via
     * registerCalendarSecret.
     *
     * @param  'graph_inbox_token_hash'  $key
     */
    protected function registerSubscriptionSecret(IntegrationAccount $account, string $key): string
    {
        $secret = Str::random(48);

        $account->forceFill([
            'settings' => array_merge($account->settings ?? [], [
                $key => hash('sha256', $secret),
            ]),
        ])->save();

        return $account->id.'.'.$secret;
    }

    protected function tokenRequest(array $extra): array
    {
        $tenant = config('integrations.microsoft.tenant_id');

        return Http::asForm()
            ->post("https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/token", array_merge([
                'client_id' => config('integrations.microsoft.client_id'),
                'client_secret' => config('integrations.microsoft.client_secret'),
                'scope' => implode(' ', config('integrations.microsoft.scopes')),
            ], $extra))
            ->throw()->json();
    }

    protected function ensureValidToken(IntegrationAccount $account): void
    {
        if ($account->tokenIsExpired()) {
            $this->refreshAccessToken($account);
            $account->refresh();
        }
    }
}
