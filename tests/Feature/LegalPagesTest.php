<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * These URLs are submitted to Google and Microsoft for OAuth app verification.
 * If they stop resolving, or fall behind an auth wall, verification fails and
 * every user's calendar and email connection stops working.
 */
class LegalPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_legal_pages_are_reachable_without_signing_in(): void
    {
        $this->get('/privacy')->assertOk();
        $this->get('/terms')->assertOk();
    }

    public function test_the_policy_text_is_in_the_server_response(): void
    {
        // Rendered by Blade rather than Inertia on purpose: a reviewer must be
        // able to read the policy without the front-end bundle having built.
        $this->get('/privacy')
            ->assertOk()
            ->assertSee('Privacy Policy')
            ->assertDontSee('data-page'); // the Inertia root element
    }

    public function test_the_privacy_policy_carries_the_google_limited_use_disclosure(): void
    {
        // Google requires this statement verbatim-in-substance for any app using
        // sensitive or restricted scopes. Without it, verification is refused.
        $this->get('/privacy')
            ->assertOk()
            ->assertSee('Google API Services User Data Policy')
            ->assertSee('Limited Use requirements');
    }

    public function test_the_privacy_policy_states_what_we_do_not_do_with_google_data(): void
    {
        $response = $this->get('/privacy')->assertOk();

        // The three commitments reviewers look for.
        $response->assertSee('do not use Google user data to develop, improve or train', false);
        $response->assertSee('do not sell Google user data', false);
        $response->assertSee('metadata', false);
    }

    public function test_each_legal_page_links_to_the_other(): void
    {
        // Reviewers navigate between them; a dead link reads as an unfinished app.
        $this->get('/privacy')->assertOk()->assertSee(route('legal.terms'), false);
        $this->get('/terms')->assertOk()->assertSee(route('legal.privacy'), false);
    }

    public function test_the_pages_name_the_operating_entity_and_a_contact_address(): void
    {
        config([
            'legal.entity' => 'Bint Technologies (Pvt) Ltd',
            'legal.privacy_email' => 'privacy@example.test',
            'legal.support_email' => 'support@example.test',
        ]);

        $this->get('/privacy')
            ->assertOk()
            ->assertSee('Bint Technologies (Pvt) Ltd')
            ->assertSee('privacy@example.test');

        $this->get('/terms')
            ->assertOk()
            ->assertSee('Bint Technologies (Pvt) Ltd')
            ->assertSee('support@example.test');
    }
}
