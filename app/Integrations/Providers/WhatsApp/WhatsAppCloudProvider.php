<?php

namespace App\Integrations\Providers\WhatsApp;

use App\Enums\MessageChannel;
use App\Integrations\Contracts\MessagingProvider;
use App\Models\IntegrationAccount;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class WhatsAppCloudProvider implements MessagingProvider
{
    private const GRAPH_BASE = 'https://graph.facebook.com/v20.0';

    public function send(IntegrationAccount $account, array $payload): Message
    {
        $phoneId = config('integrations.whatsapp.phone_number_id');
        $token = config('integrations.whatsapp.access_token');

        if (! $phoneId || ! $token) {
            throw new RuntimeException('WhatsApp Cloud is not configured (phone number id / access token).');
        }

        $to = $this->normalizeTo($payload['to']);

        $response = Http::withToken($token)
            ->post(self::GRAPH_BASE."/{$phoneId}/messages", [
                'messaging_product' => 'whatsapp',
                'to' => $to,
                'type' => 'text',
                'text' => ['body' => $payload['body_text'] ?? ''],
            ])
            ->throw()
            ->json();

        $externalId = $response['messages'][0]['id'] ?? null;

        return Message::create([
            'workspace_id' => $account->workspace_id,
            'integration_account_id' => $account->id,
            'channel' => MessageChannel::WhatsApp->value,
            'direction' => Message::DIRECTION_OUTBOUND,
            'external_id' => $externalId,
            'from_address' => $phoneId,
            'to_addresses' => [$to],
            'body_text' => $payload['body_text'] ?? null,
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }

    public function verifyWebhook(Request $request): bool
    {
        // Initial verification handshake — Meta sends GET with hub.* params.
        if ($request->isMethod('GET')) {
            $expected = config('integrations.whatsapp.verify_token');

            return $expected
                && $request->input('hub_verify_token') === $expected
                && $request->input('hub_mode') === 'subscribe';
        }

        // Inbound POST — verify X-Hub-Signature-256 against APP_SECRET-keyed HMAC.
        $signature = $request->header('X-Hub-Signature-256');
        $secret = config('integrations.whatsapp.access_token');

        if (! $signature || ! $secret) {
            return false;
        }

        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, $signature);
    }

    public function parseInbound(Request $request): array
    {
        $value = Arr::get($request->all(), 'entry.0.changes.0.value', []);
        $message = Arr::get($value, 'messages.0', []);

        $body = $message['text']['body'] ?? null;

        return [
            'channel' => MessageChannel::WhatsApp->value,
            'direction' => Message::DIRECTION_INBOUND,
            'external_id' => $message['id'] ?? null,
            'from_address' => $message['from'] ?? null,
            'to_addresses' => [$value['metadata']['display_phone_number'] ?? null],
            'body_text' => $body,
            'status' => 'received',
            'sent_at' => now(),
        ];
    }

    protected function normalizeTo(mixed $to): string
    {
        if (is_array($to)) {
            $to = Arr::first($to);
        }

        // WhatsApp Cloud wants digits-only with country code, no '+' prefix.
        return ltrim((string) $to, '+');
    }
}
