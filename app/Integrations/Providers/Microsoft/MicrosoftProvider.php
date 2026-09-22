<?php

namespace App\Integrations\Providers\Microsoft;

use App\Enums\MessageChannel;
use App\Integrations\Contracts\CalendarProvider;
use App\Integrations\Contracts\EmailProvider;
use App\Integrations\Contracts\OAuthProvider;
use App\Models\CalendarEvent;
use App\Models\IntegrationAccount;
use App\Models\Meeting;
use App\Models\Message;
use Carbon\CarbonImmutable;
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

    public function syncEvents(IntegrationAccount $account): int
    {
        $this->ensureValidToken($account);

        $persisted = 0;
        $url = $account->sync_cursor
            ?: self::GRAPH_BASE.'/me/calendar/events/delta?$select=id,subject,bodyPreview,location,start,end,attendees,isCancelled,isAllDay,recurrence,seriesMasterId,organizer,webLink,onlineMeeting';

        $deltaLink = null;

        do {
            $response = Http::withToken($account->access_token)->get($url)->throw()->json();

            foreach (($response['value'] ?? []) as $event) {
                if (! isset($event['id'])) {
                    continue;
                }

                if (! empty($event['isCancelled']) || ($event['@removed']['reason'] ?? null) === 'changed') {
                    CalendarEvent::query()
                        ->where('integration_account_id', $account->id)
                        ->where('external_id', $event['id'])
                        ->delete();

                    continue;
                }

                // meeting_id is deliberately absent: it is the link Cadence owns,
                // and must survive a sync rewriting the row.
                CalendarEvent::query()->updateOrCreate(
                    ['integration_account_id' => $account->id, 'external_id' => $event['id']],
                    $this->eventAttributes($account, $event),
                );

                $persisted++;
            }

            $url = $response['@odata.nextLink'] ?? null;
            $deltaLink = $response['@odata.deltaLink'] ?? $deltaLink;
        } while ($url);

        $account->forceFill([
            'last_synced_at' => now(),
            'sync_cursor' => $deltaLink ?: $account->sync_cursor,
            'last_error' => null,
        ])->save();

        return $persisted;
    }

    /**
     * Map a Graph event onto the columns of `calendar_events`.
     *
     * @param  array<string, mixed>  $event
     * @return array<string, mixed>
     */
    protected function eventAttributes(IntegrationAccount $account, array $event): array
    {
        return [
            'workspace_id' => $account->workspace_id,
            'title' => $event['subject'] ?? '(no title)',
            'description' => $event['bodyPreview'] ?? null,
            'location' => $event['location']['displayName'] ?? null,
            'all_day' => (bool) ($event['isAllDay'] ?? false),
            // Graph describes recurrence as an object rather than RRULE strings;
            // stored as-is, since it is only read back to flag a series.
            'recurrence' => isset($event['recurrence']) ? [$event['recurrence']] : null,
            'recurring_event_id' => $event['seriesMasterId'] ?? null,
            'organizer_email' => $event['organizer']['emailAddress']['address'] ?? null,
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

        $response = Http::withToken($account->access_token)
            ->post(self::GRAPH_BASE.'/me/events', $this->meetingPayload($meeting))
            ->throw()
            ->json();

        $this->captureConferenceUrl($meeting, $response);

        return CalendarEvent::query()->updateOrCreate(
            ['integration_account_id' => $account->id, 'external_id' => $response['id']],
            $this->eventAttributes($account, $response) + ['meeting_id' => $meeting->id],
        );
    }

    public function updateEvent(IntegrationAccount $account, CalendarEvent $event, Meeting $meeting): CalendarEvent
    {
        $this->ensureValidToken($account);

        $response = Http::withToken($account->access_token)
            ->patch(self::GRAPH_BASE.'/me/events/'.$event->external_id, $this->meetingPayload($meeting))
            ->throw()
            ->json();

        $this->captureConferenceUrl($meeting, $response);

        $event->forceFill(
            $this->eventAttributes($account, $response) + ['meeting_id' => $meeting->id]
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
            ->delete(self::GRAPH_BASE.'/me/events/'.$event->external_id);

        // Already gone upstream is the outcome we were after.
        if (! $response->successful() && ! in_array($response->status(), [404, 410], true)) {
            $response->throw();
        }

        $event->delete();
    }

    public function watchCalendar(IntegrationAccount $account): void
    {
        $this->ensureValidToken($account);

        Http::withToken($account->access_token)
            ->post(self::GRAPH_BASE.'/subscriptions', [
                'changeType' => 'created,updated,deleted',
                'notificationUrl' => config('integrations.microsoft.webhook_url'),
                'resource' => '/me/events',
                'expirationDateTime' => now()->addDays(2)->toIso8601String(),
                'clientState' => $this->registerSubscriptionSecret($account, 'graph_calendar_token_hash'),
            ])
            ->throw();
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
     * @param  'graph_inbox_token_hash'|'graph_calendar_token_hash'  $key
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
