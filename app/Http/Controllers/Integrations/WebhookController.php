<?php

namespace App\Http\Controllers\Integrations;

use App\Enums\IntegrationProvider;
use App\Http\Controllers\Controller;
use App\Integrations\IntegrationManager;
use App\Jobs\SyncIntegrationAccountInbox;
use App\Models\IntegrationAccount;
use App\Models\Message;
use App\Models\WebhookDelivery;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class WebhookController extends Controller
{
    public function __construct(protected IntegrationManager $manager) {}

    /**
     * Gmail uses Google Cloud Pub/Sub. Payload is `{message: {data: base64(json)}, subscription: ...}`.
     * The decoded data contains the email address whose mailbox changed.
     */
    public function gmail(Request $request): SymfonyResponse
    {
        $delivery = WebhookDelivery::create([
            'provider' => IntegrationProvider::Gmail->value,
            'event_type' => 'gmail.push',
            'headers' => $request->headers->all(),
            'payload' => $request->all(),
            'signature_verified' => $this->verifyGooglePubSub($request),
        ]);

        if (! $delivery->signature_verified) {
            $delivery->forceFill(['error' => 'Signature verification failed.'])->save();

            return response('Forbidden', 403);
        }

        $data = json_decode(
            base64_decode($request->input('message.data', '')) ?: '{}',
            true,
        );

        if ($email = $data['emailAddress'] ?? null) {
            IntegrationAccount::query()
                ->where('provider', IntegrationProvider::Gmail->value)
                ->where('display_name', $email)
                ->get()
                ->each(fn ($a) => SyncIntegrationAccountInbox::dispatch($a->id));
        }

        $delivery->forceFill(['processed_at' => now()])->save();

        return response('OK');
    }

    /**
     * Microsoft Graph subscription. Returns the validationToken on initial subscribe;
     * otherwise dispatches sync jobs per affected subscription.
     */
    public function microsoft(Request $request): SymfonyResponse
    {
        if ($token = $request->query('validationToken')) {
            return response($token, 200, ['Content-Type' => 'text/plain']);
        }

        $delivery = WebhookDelivery::create([
            'provider' => IntegrationProvider::Microsoft->value,
            'event_type' => 'graph.notification',
            'headers' => $request->headers->all(),
            'payload' => $request->all(),
            'signature_verified' => true,
        ]);

        foreach (($request->input('value') ?? []) as $notification) {
            $clientState = $notification['clientState'] ?? '';
            if (! str_starts_with($clientState, 'cadence-')) {
                continue;
            }
            $accountId = (int) substr($clientState, 8);

            if ($accountId > 0) {
                SyncIntegrationAccountInbox::dispatch($accountId);
            }
        }

        $delivery->forceFill(['processed_at' => now()])->save();

        return response('OK');
    }

    /**
     * Twilio SMS status + inbound callbacks (form-urlencoded). Signature in X-Twilio-Signature.
     */
    public function twilio(Request $request): SymfonyResponse
    {
        $provider = $this->manager->messaging($this->ensureAccountFor(IntegrationProvider::TwilioSms));

        $delivery = WebhookDelivery::query()->updateOrCreate(
            [
                'provider' => IntegrationProvider::TwilioSms->value,
                'external_id' => $request->input('MessageSid'),
            ],
            [
                'event_type' => 'twilio.message',
                'headers' => $request->headers->all(),
                'payload' => $request->all(),
                'signature_verified' => $provider->verifyWebhook($request),
            ],
        );

        if (! $delivery->signature_verified) {
            $delivery->forceFill(['error' => 'Signature verification failed.'])->save();

            return response('Forbidden', 403);
        }

        if ($request->filled('Body')) {
            $this->persistInbound(IntegrationProvider::TwilioSms, $provider->parseInbound($request));
        }

        $delivery->forceFill(['processed_at' => now()])->save();

        return response('OK');
    }

    /**
     * Meta WhatsApp Cloud webhook. GET handshake with hub.* params (subscribe);
     * POST inbound messages with X-Hub-Signature-256.
     */
    public function whatsapp(Request $request): SymfonyResponse
    {
        $provider = $this->manager->messaging($this->ensureAccountFor(IntegrationProvider::WhatsAppCloud));

        if ($request->isMethod('GET')) {
            return $provider->verifyWebhook($request)
                ? response((string) $request->input('hub_challenge'), 200, ['Content-Type' => 'text/plain'])
                : response('Forbidden', 403);
        }

        $delivery = WebhookDelivery::create([
            'provider' => IntegrationProvider::WhatsAppCloud->value,
            'event_type' => 'whatsapp.message',
            'headers' => $request->headers->all(),
            'payload' => $request->all(),
            'signature_verified' => $provider->verifyWebhook($request),
        ]);

        if (! $delivery->signature_verified) {
            $delivery->forceFill(['error' => 'Signature verification failed.'])->save();

            return response('Forbidden', 403);
        }

        $parsed = $provider->parseInbound($request);

        if (! empty($parsed['external_id'])) {
            $this->persistInbound(IntegrationProvider::WhatsAppCloud, $parsed);
        }

        $delivery->forceFill(['processed_at' => now()])->save();

        return response('OK');
    }

    protected function persistInbound(IntegrationProvider $provider, array $payload): void
    {
        $account = $this->ensureAccountFor($provider);

        if (! $payload['external_id'] || Message::query()
            ->where('channel', $payload['channel'])
            ->where('external_id', $payload['external_id'])
            ->exists()) {
            return;
        }

        Message::create($payload + [
            'workspace_id' => $account->workspace_id,
            'integration_account_id' => $account->id,
        ]);
    }

    protected function ensureAccountFor(IntegrationProvider $provider): IntegrationAccount
    {
        // For shared-credential providers we maintain a single workspace-level account
        // (first one found). If none exists yet, a 404 is correct — the workspace
        // hasn't enabled the integration.
        $account = IntegrationAccount::query()
            ->where('provider', $provider->value)
            ->where('status', 'active')
            ->first();

        abort_unless($account !== null, 404, "No active {$provider->value} account.");

        return $account;
    }

    protected function verifyGooglePubSub(Request $request): bool
    {
        // In production, verify the JWT bearer token attached by Google Pub/Sub.
        // For now, we accept any payload that has the expected shape — concrete
        // JWT verification lands when GOOGLE_PUBSUB_SERVICE_ACCOUNT is configured.
        return $request->has('message.data') && $request->has('subscription');
    }
}
