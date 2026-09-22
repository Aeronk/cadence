<?php

namespace Tests\Feature\Integrations;

use App\Enums\IntegrationProvider;
use App\Integrations\IntegrationManager;
use App\Integrations\Providers\Microsoft\MicrosoftProvider;
use App\Jobs\SyncIntegrationAccountCalendar;
use App\Jobs\SyncIntegrationAccountInbox;
use App\Models\CalendarSource;
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

    /**
     * Register a subscription secret the way the provider does, and hand back the
     * clientState Graph would echo back.
     */
    protected function registerSecret(IntegrationAccount $account, string $key, string $secret): string
    {
        $account->forceFill([
            'settings' => array_merge($account->settings ?? [], [
                $key => hash('sha256', $secret),
            ]),
        ])->save();

        return $account->id.'.'.$secret;
    }

    public function test_notification_dispatches_inbox_sync_for_a_verified_subscription(): void
    {
        Bus::fake();

        $account = IntegrationAccount::factory()->provider(IntegrationProvider::Microsoft)->create();
        $clientState = $this->registerSecret($account, 'graph_inbox_token_hash', 'inbox-secret');

        $this->postJson('/integrations/microsoft/webhook', [
            'value' => [['clientState' => $clientState, 'changeType' => 'created']],
        ])->assertOk();

        Bus::assertDispatched(SyncIntegrationAccountInbox::class, fn ($job) => $job->integrationAccountId === $account->id);
    }

    /**
     * Graph subscribes per calendar, so a calendar clientState names a
     * calendar_sources row rather than the account.
     */
    protected function registerCalendarSecret(IntegrationAccount $account, string $secret): string
    {
        $source = CalendarSource::create([
            'integration_account_id' => $account->id,
            'workspace_id' => $account->workspace_id,
            'external_id' => 'AAMkAG-primary',
            'name' => 'Calendar',
            'is_selected' => true,
        ]);

        $source->forceFill(['watch_channel_token_hash' => hash('sha256', $secret)])->save();

        return $source->id.'.'.$secret;
    }

    public function test_a_calendar_subscription_dispatches_a_calendar_sync_not_an_inbox_sync(): void
    {
        Bus::fake();

        $account = IntegrationAccount::factory()->provider(IntegrationProvider::Microsoft)->create();
        $clientState = $this->registerCalendarSecret($account, 'calendar-secret');

        $this->postJson('/integrations/microsoft/webhook', [
            'value' => [['clientState' => $clientState, 'changeType' => 'updated']],
        ])->assertOk();

        Bus::assertDispatched(SyncIntegrationAccountCalendar::class, fn ($job) => $job->integrationAccountId === $account->id);
        Bus::assertNotDispatched(SyncIntegrationAccountInbox::class);
    }

    /**
     * The endpoint is public and CSRF-exempt, so a clientState derived from the
     * account id alone must not be enough to trigger work.
     */
    public function test_a_guessable_client_state_is_rejected(): void
    {
        Bus::fake();

        $account = IntegrationAccount::factory()->provider(IntegrationProvider::Microsoft)->create();
        $this->registerSecret($account, 'graph_inbox_token_hash', 'inbox-secret');

        $forgeries = [
            'cadence-'.$account->id,
            'cadence-cal-'.$account->id,
            (string) $account->id,
            $account->id.'.wrong-secret',
        ];

        foreach ($forgeries as $forged) {
            $this->postJson('/integrations/microsoft/webhook', [
                'value' => [['clientState' => $forged, 'changeType' => 'created']],
            ])->assertForbidden();
        }

        Bus::assertNotDispatched(SyncIntegrationAccountInbox::class);
        Bus::assertNotDispatched(SyncIntegrationAccountCalendar::class);
    }

    public function test_notification_with_unrecognized_client_state_is_rejected(): void
    {
        Bus::fake();

        $this->postJson('/integrations/microsoft/webhook', [
            'value' => [['clientState' => 'someone-else', 'changeType' => 'created']],
        ])->assertForbidden();

        Bus::assertNotDispatched(SyncIntegrationAccountInbox::class);
    }

    public function test_an_empty_notification_list_is_accepted_as_a_no_op(): void
    {
        Bus::fake();

        // Rejecting this would count against the subscription with Graph.
        $this->postJson('/integrations/microsoft/webhook', ['value' => []])
            ->assertOk();

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
