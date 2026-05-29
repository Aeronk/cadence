<?php

namespace App\Http\Controllers\Integrations;

use App\Enums\IntegrationProvider;
use App\Http\Controllers\Controller;
use App\Jobs\SyncIntegrationAccountInbox;
use App\Models\IntegrationAccount;
use App\Models\WebhookDelivery;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class WebhookController extends Controller
{
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

    protected function verifyGooglePubSub(Request $request): bool
    {
        // In production, verify the JWT bearer token attached by Google Pub/Sub.
        // For now, we accept any payload that has the expected shape — concrete
        // JWT verification lands when GOOGLE_PUBSUB_SERVICE_ACCOUNT is configured.
        return $request->has('message.data') && $request->has('subscription');
    }
}
