<?php

namespace App\Integrations\Providers\Gmail;

use App\Enums\MessageChannel;
use App\Integrations\Contracts\EmailProvider;
use App\Integrations\Contracts\OAuthProvider;
use App\Models\IntegrationAccount;
use App\Models\Message;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GmailProvider implements EmailProvider, OAuthProvider
{
    private const AUTH_ENDPOINT = 'https://accounts.google.com/o/oauth2/v2/auth';

    private const TOKEN_ENDPOINT = 'https://oauth2.googleapis.com/token';

    private const API_BASE = 'https://gmail.googleapis.com/gmail/v1/users/me';

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
