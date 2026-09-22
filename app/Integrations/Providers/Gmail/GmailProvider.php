<?php

namespace App\Integrations\Providers\Gmail;

use App\Enums\MessageChannel;
use App\Integrations\Contracts\CalendarProvider;
use App\Integrations\Contracts\EmailProvider;
use App\Integrations\Contracts\OAuthProvider;
use App\Models\CalendarEvent;
use App\Models\IntegrationAccount;
use App\Models\Meeting;
use App\Models\Message;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class GmailProvider implements CalendarProvider, EmailProvider, OAuthProvider
{
    private const AUTH_ENDPOINT = 'https://accounts.google.com/o/oauth2/v2/auth';

    private const TOKEN_ENDPOINT = 'https://oauth2.googleapis.com/token';

    private const API_BASE = 'https://gmail.googleapis.com/gmail/v1/users/me';

    private const CALENDAR_BASE = 'https://www.googleapis.com/calendar/v3/calendars/primary';

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

    public function syncInbox(IntegrationAccount $account): int
    {
        $this->ensureValidToken($account);

        $persisted = 0;
        $pageToken = null;
        $cursor = $account->sync_cursor;
        $query = $cursor ? "after:{$cursor}" : 'newer_than:30d';

        do {
            $list = Http::withToken($account->access_token)
                ->get(self::API_BASE.'/messages', array_filter([
                    'q' => $query,
                    'maxResults' => 50,
                    'pageToken' => $pageToken,
                ]))
                ->throw()->json();

            foreach (($list['messages'] ?? []) as $stub) {
                if (Message::query()
                    ->where('channel', MessageChannel::Email->value)
                    ->where('external_id', $stub['id'])
                    ->exists()) {
                    continue;
                }

                $detail = Http::withToken($account->access_token)
                    ->get(self::API_BASE."/messages/{$stub['id']}", ['format' => 'metadata', 'metadataHeaders' => ['From', 'To', 'Subject', 'Date']])
                    ->throw()->json();

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
                    'sent_at' => now(),
                ]);

                $persisted++;
            }

            $pageToken = $list['nextPageToken'] ?? null;
        } while ($pageToken);

        $account->forceFill([
            'last_synced_at' => now(),
            'sync_cursor' => now()->format('Y/m/d'),
            'last_error' => null,
        ])->save();

        return $persisted;
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

    public function syncEvents(IntegrationAccount $account): int
    {
        $this->ensureValidToken($account);

        try {
            return $this->pullEvents($account, $account->sync_cursor);
        } catch (RequestException $e) {
            $status = $e->response->status();

            // 410 GONE means the sync token aged out; 400 means Google rejected it
            // as incompatible with the request. Either way the only recovery is a
            // full re-sync, so the cursor is dropped and the pull retried once.
            // Without this an account's calendar stops updating permanently.
            if (! $account->sync_cursor || ! in_array($status, [400, 410], true)) {
                throw $e;
            }

            $account->forceFill(['sync_cursor' => null])->save();

            return $this->pullEvents($account, null);
        }
    }

    /**
     * Read one page-set of events into `calendar_events`.
     *
     * `singleEvents=true` is sent on every request, incremental included, because
     * Google requires the parameters behind a sync token to stay identical. That
     * also means recurring series arrive already expanded into instances, each
     * carrying `recurringEventId` so they can be grouped back together.
     */
    protected function pullEvents(IntegrationAccount $account, ?string $syncToken): int
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
                ->get(self::CALENDAR_BASE.'/events', $params)
                ->throw()
                ->json();

            foreach (($response['items'] ?? []) as $event) {
                if (($event['status'] ?? '') === 'cancelled') {
                    CalendarEvent::query()
                        ->where('integration_account_id', $account->id)
                        ->where('external_id', $event['id'])
                        ->delete();

                    continue;
                }

                // meeting_id is deliberately absent from the update payload: it is
                // the link Cadence owns, and must survive a sync rewriting the row.
                CalendarEvent::query()->updateOrCreate(
                    ['integration_account_id' => $account->id, 'external_id' => $event['id']],
                    $this->eventAttributes($account, $event),
                );

                $persisted++;
            }

            $pageToken = $response['nextPageToken'] ?? null;
            $nextSyncToken = $response['nextSyncToken'] ?? $nextSyncToken;
        } while ($pageToken);

        $account->forceFill([
            'last_synced_at' => now(),
            'sync_cursor' => $nextSyncToken ?: $account->sync_cursor,
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
    protected function eventAttributes(IntegrationAccount $account, array $event): array
    {
        $start = $event['start'] ?? [];
        $end = $event['end'] ?? [];

        return [
            'workspace_id' => $account->workspace_id,
            'etag' => $event['etag'] ?? null,
            'title' => $event['summary'] ?? '(no title)',
            'description' => $event['description'] ?? null,
            'location' => $event['location'] ?? null,
            'all_day' => ! isset($start['dateTime']) && isset($start['date']),
            'recurrence' => $event['recurrence'] ?? null,
            'recurring_event_id' => $event['recurringEventId'] ?? null,
            'organizer_email' => $event['organizer']['email'] ?? null,
            'html_link' => $event['htmlLink'] ?? null,
            'conference_url' => $event['hangoutLink'] ?? null,
            'starts_at' => $this->parseEventTime($start),
            'ends_at' => $this->parseEventTime($end),
            'attendees' => collect($event['attendees'] ?? [])
                ->filter(fn ($attendee) => ! empty($attendee['email']))
                ->map(fn ($attendee) => [
                    'email' => $attendee['email'],
                    'name' => $attendee['displayName'] ?? null,
                    'response_status' => $attendee['responseStatus'] ?? 'needsAction',
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
            ->post(
                self::CALENDAR_BASE.'/events?'.http_build_query($this->writeQuery($meeting)),
                $this->meetingPayload($meeting),
            )
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
            ->patch(
                self::CALENDAR_BASE.'/events/'.$event->external_id.'?'.http_build_query($this->writeQuery($meeting)),
                $this->meetingPayload($meeting),
            )
            ->throw()
            ->json();

        $this->captureConferenceUrl($meeting, $response);

        $event->forceFill(
            $this->eventAttributes($account, $response) + ['meeting_id' => $meeting->id]
        )->save();

        return $event;
    }

    public function deleteEvent(IntegrationAccount $account, CalendarEvent $event): void
    {
        $this->ensureValidToken($account);

        $response = Http::withToken($account->access_token)
            ->delete(self::CALENDAR_BASE.'/events/'.$event->external_id, [
                // Tell the attendees the meeting is off.
                'sendUpdates' => 'all',
            ]);

        // Already gone upstream is the outcome we were after.
        if (! $response->successful() && ! in_array($response->status(), [404, 410], true)) {
            $response->throw();
        }

        $event->delete();
    }

    public function watchCalendar(IntegrationAccount $account): void
    {
        // Push needs a publicly reachable HTTPS callback on a domain Google has
        // verified. Without one configured we fall back to scheduled polling,
        // which is why this returns quietly instead of failing the sync.
        if (! config('integrations.google.calendar_push_enabled')) {
            return;
        }

        $this->ensureValidToken($account);

        $channelId = 'cadence-cal-'.$account->id.'-'.Str::random(16);

        $response = Http::withToken($account->access_token)
            ->post(self::CALENDAR_BASE.'/events/watch', [
                'id' => $channelId,
                'type' => 'web_hook',
                'address' => config('integrations.google.calendar_webhook_url')
                    ?: route('integrations.webhooks.google-calendar'),
                // Echoed back on every notification; how the account is identified.
                'token' => 'account='.$account->id,
            ])
            ->throw()
            ->json();

        $account->forceFill([
            'settings' => array_merge($account->settings ?? [], [
                'calendar_channel_id' => $channelId,
                'calendar_resource_id' => $response['resourceId'] ?? null,
                'calendar_channel_expires_at' => isset($response['expiration'])
                    ? CarbonImmutable::createFromTimestampMs((int) $response['expiration'])->toIso8601String()
                    : null,
            ]),
        ])->save();
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
