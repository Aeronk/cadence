<?php

namespace Tests\Feature\Integrations;

use App\Enums\IntegrationProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            'integrations.google.redirect_uri' => 'https://cadence.test/integrations/gmail/callback',
            'integrations.microsoft.client_id' => 'ms-client-id',
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
