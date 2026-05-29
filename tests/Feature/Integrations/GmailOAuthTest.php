<?php

namespace Tests\Feature\Integrations;

use App\Enums\IntegrationProvider;
use App\Jobs\SyncIntegrationAccountInbox;
use App\Models\IntegrationAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GmailOAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('integrations.google.client_id', 'test-client');
        config()->set('integrations.google.client_secret', 'test-secret');
        config()->set('integrations.google.redirect_uri', 'http://localhost/integrations/gmail/callback');
    }

    public function test_redirect_sends_user_to_google_consent_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/integrations/gmail/connect');

        $response->assertRedirect();
        $this->assertStringContainsString('accounts.google.com', $response->headers->get('Location'));
        $this->assertStringContainsString('client_id=test-client', $response->headers->get('Location'));
        $this->assertNotNull(session('integration_oauth_state'));
    }

    public function test_callback_exchanges_code_and_persists_account(): void
    {
        Bus::fake();
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'AT',
                'refresh_token' => 'RT',
                'expires_in' => 3600,
                'scope' => 'https://www.googleapis.com/auth/gmail.readonly',
            ]),
            'openidconnect.googleapis.com/v1/userinfo' => Http::response([
                'sub' => 'google-user-1',
                'email' => 'aaron@example.com',
            ]),
        ]);

        $user = User::factory()->create();
        $this->actingAs($user)->withSession([
            'integration_oauth_state' => ['state' => 'abc', 'provider' => 'gmail'],
        ]);

        $this->get('/integrations/gmail/callback?code=AUTHCODE&state=abc')
            ->assertRedirect(route('integrations.index'));

        $this->assertDatabaseHas('integration_accounts', [
            'user_id' => $user->id,
            'provider' => IntegrationProvider::Gmail->value,
            'external_account_id' => 'google-user-1',
            'display_name' => 'aaron@example.com',
        ]);

        $account = IntegrationAccount::first();
        $this->assertSame('AT', $account->access_token);
        $this->assertSame('RT', $account->refresh_token);

        Bus::assertDispatched(SyncIntegrationAccountInbox::class);
    }

    public function test_callback_rejects_mismatched_state(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->withSession([
            'integration_oauth_state' => ['state' => 'correct', 'provider' => 'gmail'],
        ]);

        $this->get('/integrations/gmail/callback?code=X&state=tampered')
            ->assertForbidden();
    }
}
