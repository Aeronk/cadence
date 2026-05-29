<?php

namespace Tests\Unit\Integrations;

use App\Enums\IntegrationProvider;
use App\Integrations\Contracts\EmailProvider;
use App\Integrations\Contracts\OAuthProvider;
use App\Integrations\IntegrationManager;
use App\Models\IntegrationAccount;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class FakeGmailProvider implements EmailProvider, OAuthProvider
{
    public function authorizationUrl(string $state, ?string $redirectUri = null): string
    {
        return 'https://accounts.google.com/o/oauth2/auth?state='.$state;
    }

    public function exchangeCode(string $code, ?string $redirectUri = null): array
    {
        return ['access_token' => 'a', 'refresh_token' => 'r', 'expires_in' => 3600, 'external_account_id' => 'x'];
    }

    public function refreshAccessToken(IntegrationAccount $account): array
    {
        return ['access_token' => 'a2', 'refresh_token' => null, 'expires_in' => 3600, 'external_account_id' => 'x'];
    }

    public function syncInbox(IntegrationAccount $account): int
    {
        return 0;
    }

    public function send(IntegrationAccount $account, array $payload): Message
    {
        return Message::factory()->outbound()->create();
    }

    public function watchInbox(IntegrationAccount $account): void {}
}

class IntegrationManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_resolve_oauth_provider(): void
    {
        $manager = new IntegrationManager;
        $manager->bind(IntegrationProvider::Gmail, FakeGmailProvider::class);

        $provider = $manager->oauth(IntegrationProvider::Gmail);

        $this->assertInstanceOf(OAuthProvider::class, $provider);
        $this->assertStringContainsString('state=xyz', $provider->authorizationUrl('xyz'));
    }

    public function test_can_resolve_email_provider_from_account(): void
    {
        $manager = new IntegrationManager;
        $manager->bind(IntegrationProvider::Gmail, FakeGmailProvider::class);

        $account = IntegrationAccount::factory()->provider(IntegrationProvider::Gmail)->create();
        $provider = $manager->email($account);

        $this->assertInstanceOf(EmailProvider::class, $provider);
    }

    public function test_throws_when_no_binding(): void
    {
        $manager = new IntegrationManager;

        $this->expectException(\InvalidArgumentException::class);
        $manager->oauth(IntegrationProvider::Gmail);
    }

    public function test_throws_when_provider_does_not_implement_requested_capability(): void
    {
        $manager = new IntegrationManager;
        $manager->bind(IntegrationProvider::TwilioSms, FakeGmailProvider::class);

        $account = IntegrationAccount::factory()->provider(IntegrationProvider::TwilioSms)->create();

        $this->expectException(RuntimeException::class);
        $manager->messaging($account);
    }
}
