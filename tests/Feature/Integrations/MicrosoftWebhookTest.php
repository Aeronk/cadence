<?php

namespace Tests\Feature\Integrations;

use App\Enums\IntegrationProvider;
use App\Integrations\IntegrationManager;
use App\Integrations\Providers\Microsoft\MicrosoftProvider;
use App\Jobs\SyncIntegrationAccountInbox;
use App\Models\IntegrationAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class MicrosoftWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(IntegrationManager::class)->bind(IntegrationProvider::Microsoft, MicrosoftProvider::class);
    }

    public function test_validation_token_handshake_returns_token_verbatim(): void
    {
        $this->get('/integrations/microsoft/webhook?validationToken=abc123')
            ->assertOk()
            ->assertSee('abc123');
    }

    public function test_notification_dispatches_sync_job_for_matching_account(): void
    {
        Bus::fake();

        $account = IntegrationAccount::factory()->provider(IntegrationProvider::Microsoft)->create();

        $this->postJson('/integrations/microsoft/webhook', [
            'value' => [
                ['clientState' => 'cadence-'.$account->id, 'changeType' => 'created'],
            ],
        ])->assertOk();

        Bus::assertDispatched(SyncIntegrationAccountInbox::class, fn ($job) => $job->integrationAccountId === $account->id);
    }

    public function test_notification_with_unrecognized_client_state_is_ignored(): void
    {
        Bus::fake();

        $this->postJson('/integrations/microsoft/webhook', [
            'value' => [['clientState' => 'someone-else', 'changeType' => 'created']],
        ])->assertOk();

        Bus::assertNotDispatched(SyncIntegrationAccountInbox::class);
    }

    public function test_delivery_is_logged(): void
    {
        $this->postJson('/integrations/microsoft/webhook', ['value' => []])
            ->assertOk();

        $this->assertDatabaseHas('webhook_deliveries', [
            'provider' => IntegrationProvider::Microsoft->value,
        ]);
    }
}
