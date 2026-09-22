<?php

namespace App\Http\Controllers\Integrations;

use App\Enums\IntegrationProvider;
use App\Http\Controllers\Controller;
use App\Integrations\IntegrationManager;
use App\Jobs\SyncIntegrationAccountCalendar;
use App\Jobs\SyncIntegrationAccountInbox;
use App\Models\IntegrationAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

class OAuthController extends Controller
{
    public function __construct(protected IntegrationManager $manager) {}

    public function redirect(Request $request, string $provider): RedirectResponse
    {
        $providerEnum = $this->resolveProvider($provider);

        // Sending someone to Google with no client_id gets them an opaque
        // "OAuth client was not found" that says nothing about the real cause.
        if (! $providerEnum->credentialsConfigured()) {
            return redirect()->route('integrations.index')->with(
                'flash.error',
                $providerEnum->label().' is not configured on this server yet. '
                    .'An administrator needs to add the OAuth client id and secret.',
            );
        }

        $state = Str::random(40);
        $request->session()->put('integration_oauth_state', [
            'state' => $state,
            'provider' => $providerEnum->value,
        ]);

        return redirect()->away(
            $this->manager->oauth($providerEnum)->authorizationUrl($state)
        );
    }

    public function callback(Request $request, string $provider): RedirectResponse
    {
        $providerEnum = $this->resolveProvider($provider);
        $session = $request->session()->pull('integration_oauth_state');

        abort_unless(
            is_array($session)
                && hash_equals($session['state'], $request->string('state')->toString())
                && $session['provider'] === $providerEnum->value,
            403,
            'Invalid OAuth state.'
        );

        if ($request->has('error')) {
            return redirect()->route('integrations.index')
                ->with('flash.error', 'Connection cancelled: '.$request->string('error_description'));
        }

        try {
            $tokens = $this->manager->oauth($providerEnum)->exchangeCode($request->string('code'));
        } catch (Throwable $e) {
            return redirect()->route('integrations.index')
                ->with('flash.error', 'Could not connect: '.$e->getMessage());
        }

        $user = $request->user();
        $account = IntegrationAccount::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'provider' => $providerEnum->value,
                'external_account_id' => $tokens['external_account_id'],
            ],
            [
                'workspace_id' => $user->currentWorkspace()->id,
                'display_name' => $tokens['display_name'],
                'access_token' => $tokens['access_token'],
                'refresh_token' => $tokens['refresh_token'],
                'token_expires_at' => $tokens['expires_in']
                    ? now()->addSeconds((int) $tokens['expires_in'])
                    : null,
                'scopes' => $tokens['scope'] ? explode(' ', $tokens['scope']) : null,
                'status' => 'active',
                'last_error' => null,
            ]
        );

        SyncIntegrationAccountInbox::dispatch($account->id)->afterResponse();
        // Both providers carry a calendar scope, so a fresh connection should
        // populate the calendar too rather than waiting for the next poll. The
        // list of calendars is read first: without it there is nothing to pull
        // events from, and nothing for the user to choose between.
        SyncIntegrationAccountCalendar::dispatch($account->id, refreshCalendarList: true)
            ->afterResponse();

        return redirect()->route('integrations.index')
            ->with('flash.success', $providerEnum->label().' connected.');
    }

    /**
     * Resolve a URL segment to the provider whose OAuth flow it belongs to.
     *
     * `google_calendar` and `google_meet` are capabilities of the Google
     * connection rather than connections of their own, so they map onto Gmail
     * instead of 404ing — a link to either is a reasonable thing for someone to
     * follow, and one Google authorisation is what actually grants them.
     */
    protected function resolveProvider(string $key): IntegrationProvider
    {
        $provider = IntegrationProvider::tryFrom($key);

        return $provider?->connectsVia() ?? abort(404);
    }
}
