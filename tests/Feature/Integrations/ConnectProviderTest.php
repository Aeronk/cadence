<?php

namespace Tests\Feature\Integrations;

use App\Enums\IntegrationProvider;
use App\Jobs\SyncIntegrationAccountCalendar;
use App\Jobs\SyncIntegrationAccountInbox;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Which providers can be connected, and what happens to the ones that cannot.
 *
 * The integrations page used to render a clickable tile for every case of the
 * enum, but the connect route only ever accepted gmail and microsoft — so
 * Google Calendar, Twilio and WhatsApp were links straight to a 404.
 */
class ConnectProviderTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        config([
            'integrations.google.client_id' => 'test-client-id',
            'integrations.google.client_secret' => 'test-client-secret',
            'integrations.google.redirect_uri' => 'https://cadence.test/integrations/gmail/callback',
            'integrations.microsoft.client_id' => 'ms-client-id',
            'integrations.microsoft.client_secret' => 'ms-client-secret',
            'integrations.microsoft.redirect_uri' => 'https://cadence.test/integrations/microsoft/callback',
        ]);
    }

    public function test_google_calendar_connects_through_the_google_flow_rather_than_404ing(): void
    {
        // One Google authorisation grants Gmail and Calendar together, so this
        // URL is a reasonable thing to follow and must not dead-end.
        $response = $this->actingAs($this->user)
            ->get('/integrations/google_calendar/connect')
            ->assertRedirect();

        $this->assertStringStartsWith(
            'https://accounts.google.com/o/oauth2/v2/auth',
            $response->headers->get('Location'),
        );

        // And it asks for the calendar scope, which is what makes it work.
        $this->assertStringContainsString(
            urlencode('https://www.googleapis.com/auth/calendar'),
            $response->headers->get('Location'),
        );
    }

    public function test_the_google_aliases_all_land_on_the_same_connection(): void
    {
        foreach (['gmail', 'google_calendar', 'google_meet'] as $slug) {
            $this->actingAs($this->user)
                ->get("/integrations/{$slug}/connect")
                ->assertRedirect();

            // Whichever door you come through, the account being authorised is
            // the Google one — never a second, separate connection.
            $this->assertSame(
                IntegrationProvider::Gmail->value,
                session('integration_oauth_state')['provider'],
            );
        }
    }

    public function test_a_provider_with_no_oauth_flow_is_not_connectable(): void
    {
        foreach (['twilio_sms', 'whatsapp_cloud', 'zoom'] as $slug) {
            $this->actingAs($this->user)
                ->get("/integrations/{$slug}/connect")
                ->assertNotFound();
        }
    }

    public function test_an_unknown_provider_is_still_rejected(): void
    {
        $this->actingAs($this->user)
            ->get('/integrations/dropbox/connect')
            ->assertNotFound();
    }

    public function test_the_page_only_offers_tiles_that_can_actually_connect(): void
    {
        $this->actingAs($this->user)
            ->get(route('integrations.index'))
            ->assertOk()
            ->assertInertia(function (Assert $page) {
                $providers = collect($page->toArray()['props']['available_providers']);

                $connectable = $providers->where('connectable', true)->pluck('value')->all();
                $this->assertSame(['gmail', 'microsoft'], $connectable);

                // Everything else has to explain itself rather than look broken.
                $providers->where('connectable', false)->each(function ($p) {
                    $this->assertNotNull(
                        $p['unavailable_reason'],
                        "{$p['value']} is not connectable but gives no reason why",
                    );
                });
            });
    }

    public function test_connecting_without_credentials_says_so_instead_of_sending_you_to_google(): void
    {
        config([
            'integrations.google.client_id' => null,
            'integrations.google.client_secret' => null,
        ]);

        // http_build_query drops a null client_id, so the user would otherwise
        // arrive at Google and be told "The OAuth client was not found" — an
        // error that points nowhere near the actual cause.
        $this->actingAs($this->user)
            ->get('/integrations/gmail/connect')
            ->assertRedirect(route('integrations.index'))
            ->assertSessionHas('flash.error');
    }

    public function test_an_unconfigured_provider_is_not_offered_as_connectable(): void
    {
        config([
            'integrations.google.client_id' => null,
            'integrations.google.client_secret' => null,
        ]);

        $this->actingAs($this->user)
            ->get(route('integrations.index'))
            ->assertOk()
            ->assertInertia(function (Assert $page) {
                $gmail = collect($page->toArray()['props']['available_providers'])
                    ->firstWhere('value', 'gmail');

                $this->assertFalse($gmail['connectable']);
                $this->assertSame('Not configured on this server', $gmail['unavailable_reason']);
            });
    }

    public function test_calendar_only_mode_never_asks_for_the_gmail_scopes(): void
    {
        // The Gmail scopes are Google's "restricted" tier and pull an annual
        // third-party security assessment into verification. Calendar does not.
        $location = $this->actingAs($this->user)
            ->get('/integrations/gmail/connect')
            ->assertRedirect()
            ->headers->get('Location');

        $this->assertStringContainsString(urlencode('auth/calendar'), $location);
        $this->assertStringNotContainsString('gmail.readonly', $location);
        $this->assertStringNotContainsString('gmail.send', $location);
    }

    public function test_connecting_does_not_start_a_mail_sync_while_it_is_switched_off(): void
    {
        Bus::fake();

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'at', 'refresh_token' => 'rt', 'expires_in' => 3600,
            ]),
            'openidconnect.googleapis.com/*' => Http::response([
                'sub' => 'google-user-1', 'email' => 'someone@example.com',
            ]),
        ]);

        $this->actingAs($this->user)
            ->withSession(['integration_oauth_state' => [
                'state' => 'abc',
                'provider' => IntegrationProvider::Gmail->value,
            ]])
            ->get('/integrations/gmail/callback?code=xyz&state=abc')
            ->assertRedirect(route('integrations.index'));

        // We never requested the mail scopes, so syncing mail could only fail.
        Bus::assertNotDispatched(SyncIntegrationAccountInbox::class);
        Bus::assertDispatched(SyncIntegrationAccountCalendar::class);
    }

    public function test_turning_mail_sync_back_on_restores_the_gmail_scopes(): void
    {
        config([
            'integrations.google.sync_inbox' => true,
            'integrations.google.scopes' => [
                'openid', 'email', 'profile',
                'https://www.googleapis.com/auth/calendar',
                'https://www.googleapis.com/auth/gmail.readonly',
                'https://www.googleapis.com/auth/gmail.send',
            ],
        ]);

        $location = $this->actingAs($this->user)
            ->get('/integrations/gmail/connect')
            ->assertRedirect()
            ->headers->get('Location');

        $this->assertStringContainsString('gmail.readonly', $location);
        $this->assertTrue(IntegrationProvider::Gmail->syncsInbox());
    }

    public function test_calendar_is_advertised_as_part_of_the_google_connection(): void
    {
        // Someone looking for calendar sync has to be able to tell which tile
        // gives it to them.
        $this->assertStringContainsString('Calendar', IntegrationProvider::Gmail->label());
        $this->assertSame(
            'Included when you connect Gmail',
            IntegrationProvider::GoogleCalendar->unavailableReason(),
        );
    }
}
