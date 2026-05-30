<?php

namespace App\Integrations\Providers\Twilio;

use App\Enums\MessageChannel;
use App\Integrations\Contracts\MessagingProvider;
use App\Models\IntegrationAccount;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class TwilioProvider implements MessagingProvider
{
    public function send(IntegrationAccount $account, array $payload): Message
    {
        $sid = config('integrations.twilio.account_sid');
        $token = config('integrations.twilio.auth_token');
        $from = $payload['from'] ?? config('integrations.twilio.from_number');

        if (! $sid || ! $token || ! $from) {
            throw new RuntimeException('Twilio is not configured (account sid / auth token / from number).');
        }

        $to = $this->normalizeTo($payload['to']);

        $response = Http::withBasicAuth($sid, $token)
            ->asForm()
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                'From' => $from,
                'To' => $to,
                'Body' => $payload['body_text'] ?? '',
            ])
            ->throw()
            ->json();

        return Message::create([
            'workspace_id' => $account->workspace_id,
            'integration_account_id' => $account->id,
            'channel' => MessageChannel::Sms->value,
            'direction' => Message::DIRECTION_OUTBOUND,
            'external_id' => $response['sid'],
            'from_address' => $from,
            'to_addresses' => [$to],
            'body_text' => $payload['body_text'] ?? null,
            'status' => $response['status'] ?? 'queued',
            'sent_at' => now(),
        ]);
    }

    public function verifyWebhook(Request $request): bool
    {
        $signature = $request->header('X-Twilio-Signature');
        $token = config('integrations.twilio.auth_token');

        if (! $signature || ! $token) {
            return false;
        }

        // Twilio signs the full URL + sorted POST params concatenated together,
        // then base64(HMAC-SHA1(url+params, auth_token)).
        $url = $request->fullUrl();
        $params = $request->post();
        ksort($params);
        $data = $url;
        foreach ($params as $key => $value) {
            $data .= $key.$value;
        }

        $expected = base64_encode(hash_hmac('sha1', $data, $token, true));

        return hash_equals($expected, $signature);
    }

    public function parseInbound(Request $request): array
    {
        return [
            'channel' => MessageChannel::Sms->value,
            'direction' => Message::DIRECTION_INBOUND,
            'external_id' => $request->input('MessageSid') ?: $request->input('SmsSid'),
            'from_address' => $request->input('From'),
            'to_addresses' => [$request->input('To')],
            'body_text' => $request->input('Body'),
            'status' => 'received',
            'sent_at' => now(),
        ];
    }

    protected function normalizeTo(mixed $to): string
    {
        if (is_array($to)) {
            return (string) Arr::first($to);
        }

        return (string) $to;
    }
}
