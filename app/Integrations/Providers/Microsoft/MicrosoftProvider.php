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
                    'sent_at' => isset($msg['receivedDateTime']) ? \Carbon\CarbonImmutable::parse($msg['receivedDateTime']) : now(),
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
                'clientState' => 'cadence-'.$account->id,
            ])
            ->throw();
    }

    public function syncEvents(IntegrationAccount $account): int
    {
        $this->ensureValidToken($account);

        $persisted = 0;
        $url = $account->sync_cursor
            ?: self::GRAPH_BASE.'/me/calendar/events/delta?$select=id,subject,bodyPreview,location,start,end,attendees,isCancelled';

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

                CalendarEvent::query()->updateOrCreate(
                    ['integration_account_id' => $account->id, 'external_id' => $event['id']],
                    [
                        'workspace_id' => $account->workspace_id,
                        'title' => $event['subject'] ?? '(no title)',
                        'description' => $event['bodyPreview'] ?? null,
                        'location' => $event['location']['displayName'] ?? null,
                        'starts_at' => isset($event['start']['dateTime']) ? CarbonImmutable::parse($event['start']['dateTime']) : null,
                        'ends_at' => isset($event['end']['dateTime']) ? CarbonImmutable::parse($event['end']['dateTime']) : null,
                        'attendees' => collect($event['attendees'] ?? [])
                            ->pluck('emailAddress.address')->filter()->values()->all(),
                        'sync_status' => 'synced',
                    ]
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

    public function createEvent(IntegrationAccount $account, Meeting $meeting): CalendarEvent
    {
        $this->ensureValidToken($account);

        $response = Http::withToken($account->access_token)
            ->post(self::GRAPH_BASE.'/me/events', $this->meetingPayload($meeting))
            ->throw()
            ->json();

        return CalendarEvent::query()->updateOrCreate(
            ['integration_account_id' => $account->id, 'external_id' => $response['id']],
            [
                'workspace_id' => $account->workspace_id,
                'meeting_id' => $meeting->id,
                'title' => $meeting->title,
                'description' => $meeting->description,
                'location' => $meeting->location,
                'starts_at' => $meeting->starts_at,
                'ends_at' => $meeting->ends_at,
                'attendees' => $meeting->attendees()->pluck('email')->all(),
                'sync_status' => 'synced',
            ]
        );
    }

    public function updateEvent(IntegrationAccount $account, CalendarEvent $event, Meeting $meeting): CalendarEvent
    {
        $this->ensureValidToken($account);

        Http::withToken($account->access_token)
            ->patch(self::GRAPH_BASE.'/me/events/'.$event->external_id, $this->meetingPayload($meeting))
            ->throw();

        $event->forceFill([
            'title' => $meeting->title,
            'description' => $meeting->description,
            'location' => $meeting->location,
            'starts_at' => $meeting->starts_at,
            'ends_at' => $meeting->ends_at,
            'sync_status' => 'synced',
        ])->save();

        return $event;
    }

    public function deleteEvent(IntegrationAccount $account, CalendarEvent $event): void
    {
        $this->ensureValidToken($account);

        Http::withToken($account->access_token)
            ->delete(self::GRAPH_BASE.'/me/events/'.$event->external_id)
            ->throw();

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
                'clientState' => 'cadence-cal-'.$account->id,
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

        return [
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
