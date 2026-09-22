<?php

namespace App\Http\Controllers\Integrations;

use App\Enums\IntegrationProvider;
use App\Http\Controllers\Controller;
use App\Integrations\IntegrationManager;
use App\Jobs\SyncIntegrationAccountCalendar;
use App\Jobs\SyncIntegrationAccountInbox;
use App\Models\CalendarSource;
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

        $notifications = (array) ($request->input('value') ?? []);
        $verified = 0;

        foreach ($notifications as $notification) {
            $match = $this->authenticateGraphSubscription(
                (string) ($notification['clientState'] ?? '')
            );

            if (! $match) {
                continue;
            }

            [$accountId, $kind] = $match;
            $verified++;

            // Previously every notification queued an inbox sync, so calendar
            // subscriptions refreshed the wrong thing.
            match ($kind) {
                'calendar' => SyncIntegrationAccountCalendar::dispatch($accountId),
                default => SyncIntegrationAccountInbox::dispatch($accountId),
            };
        }

        $delivery = WebhookDelivery::create([
            'provider' => IntegrationProvider::Microsoft->value,
            'event_type' => 'graph.notification',
            'headers' => $request->headers->all(),
            'payload' => $request->all(),
            'signature_verified' => $verified > 0,
        ]);

        if ($notifications !== [] && $verified === 0) {
            $delivery->forceFill(['error' => 'No notification carried a verifiable clientState.'])->save();

            return response('Forbidden', 403);
        }

        $delivery->forceFill(['processed_at' => now()])->save();

        return response('OK');
    }

    /**
     * Google Calendar push notification.
     *
     * The body is empty; everything arrives in headers. `X-Goog-Channel-Token`
     * carries the secret minted in watchCalendar and is the only thing
     * authenticating the caller, since this route is public and CSRF-exempt.
     * The first notification after subscribing is a `sync` handshake and carries
     * no change, so it is acknowledged without work.
     */
    public function googleCalendar(Request $request): SymfonyResponse
    {
        $state = (string) $request->header('X-Goog-Resource-State', '');
        $channelId = (string) $request->header('X-Goog-Channel-Id', '');

        $source = $this->authenticateCalendarChannel(
            (string) $request->header('X-Goog-Channel-Token', '')
        );

        $delivery = WebhookDelivery::create([
            'provider' => IntegrationProvider::Gmail->value,
            'event_type' => 'google.calendar.'.($state ?: 'unknown'),
            'external_id' => $channelId ?: null,
            'headers' => $request->headers->all(),
            'payload' => $request->all(),
            'signature_verified' => $source !== null,
        ]);

        if (! $source) {
            $delivery->forceFill(['error' => 'Channel token did not verify.'])->save();

            // Deliberately uniform: never reveal whether the account existed.
            return response('Forbidden', 403);
        }

        if ($state !== 'sync') {
            SyncIntegrationAccountCalendar::dispatch($source->integration_account_id);
        }

        $delivery->forceFill(['processed_at' => now()])->save();

        return response('OK');
    }

    /**
     * Resolve a calendar channel token to the calendar it was minted for, or
     * null if it does not verify.
     *
     * The token is `<calendarSourceId>.<secret>`. The id only selects the row —
     * it is sequential and guessable, so it is never sufficient on its own. The
     * secret is compared against the stored hash in constant time; anything else
     * would let an unauthenticated caller trigger sync jobs at will.
     */
    protected function authenticateCalendarChannel(string $token): ?CalendarSource
    {
        [$sourceId, $secret] = $this->splitToken($token);

        if ($sourceId === null) {
            return null;
        }

        $source = CalendarSource::query()->find($sourceId);
        $expected = $source?->watch_channel_token_hash;

        if (! is_string($expected) || $expected === '') {
            return null;
        }

        return hash_equals($expected, hash('sha256', $secret))
            ? $source
            : null;
    }

    /**
     * Split an `<id>.<secret>` token, rejecting anything malformed.
     *
     * @return array{0: int|null, 1: string}
     */
    protected function splitToken(string $token): array
    {
        if (! str_contains($token, '.')) {
            return [null, ''];
        }

        [$id, $secret] = explode('.', $token, 2);

        if (! ctype_digit($id) || $secret === '') {
            return [null, ''];
        }

        return [(int) $id, $secret];
    }

    /**
     * Resolve a Graph subscription clientState to the account it belongs to and
     * what it is a subscription for, or null if it does not verify.
     *
     * Both kinds are shaped `<id>.<secret>`, where only the secret authorises.
     * A calendar subscription's id is a calendar_sources row, since Graph
     * subscribes per calendar; an inbox subscription's is the account itself.
     * The secret is compared in constant time either way.
     *
     * @return array{0: int, 1: string}|null
     */
    protected function authenticateGraphSubscription(string $clientState): ?array
    {
        [$id, $secret] = $this->splitToken($clientState);

        if ($id === null) {
            return null;
        }

        $presented = hash('sha256', $secret);

        $source = CalendarSource::query()->find($id);
        $calendarHash = $source?->watch_channel_token_hash;

        if (is_string($calendarHash) && $calendarHash !== '' && hash_equals($calendarHash, $presented)) {
            return [$source->integration_account_id, 'calendar'];
        }

        $account = IntegrationAccount::query()->find($id);
        $inboxHash = $account?->settings['graph_inbox_token_hash'] ?? null;

        if (is_string($inboxHash) && $inboxHash !== '' && hash_equals($inboxHash, $presented)) {
            return [$account->id, 'inbox'];
        }

        return null;
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
