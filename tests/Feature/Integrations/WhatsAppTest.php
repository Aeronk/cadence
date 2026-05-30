<?php

namespace Tests\Feature\Integrations;

use App\Enums\IntegrationProvider;
use App\Enums\MessageChannel;
use App\Integrations\IntegrationManager;
use App\Integrations\Providers\WhatsApp\WhatsAppCloudProvider;
use App\Models\IntegrationAccount;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsAppTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(IntegrationManager::class)->bind(IntegrationProvider::WhatsAppCloud, WhatsAppCloudProvider::class);

        config()->set('integrations.whatsapp.phone_number_id', '1234567890');
        config()->set('integrations.whatsapp.access_token', 'EAAtest');
        config()->set('integrations.whatsapp.verify_token', 'cadence-verify');
    }

    public function test_send_posts_to_meta_and_persists_message(): void
    {
        Http::fake([
            'graph.facebook.com/v20.0/1234567890/messages' => Http::response([
                'messages' => [['id' => 'wamid.abc']],
            ]),
        ]);

        $account = IntegrationAccount::factory()->provider(IntegrationProvider::WhatsAppCloud)->create();

        $provider = app(IntegrationManager::class)->messaging($account);
        $message = $provider->send($account, [
            'to' => '+263775391733',
            'body_text' => 'hello',
        ]);

        $this->assertSame(MessageChannel::WhatsApp, $message->channel);
        $this->assertSame(Message::DIRECTION_OUTBOUND, $message->direction);
        $this->assertSame('wamid.abc', $message->external_id);

        Http::assertSent(fn ($req) => $req->data()['to'] === '263775391733'
            && $req->data()['type'] === 'text');
    }

    public function test_verify_handshake_returns_challenge_when_token_matches(): void
    {
        IntegrationAccount::factory()->provider(IntegrationProvider::WhatsAppCloud)->create();

        $this->get('/integrations/whatsapp/webhook?hub_mode=subscribe&hub_verify_token=cadence-verify&hub_challenge=42')
            ->assertOk()
            ->assertSee('42');
    }

    public function test_verify_handshake_rejects_bad_token(): void
    {
        IntegrationAccount::factory()->provider(IntegrationProvider::WhatsAppCloud)->create();

        $this->get('/integrations/whatsapp/webhook?hub_mode=subscribe&hub_verify_token=wrong&hub_challenge=42')
            ->assertForbidden();
    }

    public function test_inbound_message_with_valid_signature_persists(): void
    {
        IntegrationAccount::factory()->provider(IntegrationProvider::WhatsAppCloud)->create();

        $body = [
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'metadata' => ['display_phone_number' => '263775391733'],
                        'messages' => [[
                            'id' => 'wamid.inbound1',
                            'from' => '263775391999',
                            'text' => ['body' => 'hi from whatsapp'],
                        ]],
                    ],
                ]],
            ]],
        ];

        $payload = json_encode($body);
        $signature = 'sha256='.hash_hmac('sha256', $payload, 'EAAtest');

        $this->call('POST', '/integrations/whatsapp/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => $signature,
        ], $payload)->assertOk();

        $this->assertDatabaseHas('messages', [
            'channel' => MessageChannel::WhatsApp->value,
            'direction' => Message::DIRECTION_INBOUND,
            'external_id' => 'wamid.inbound1',
            'body_text' => 'hi from whatsapp',
        ]);
    }

    public function test_inbound_rejects_bad_signature(): void
    {
        IntegrationAccount::factory()->provider(IntegrationProvider::WhatsAppCloud)->create();

        $body = ['entry' => [['changes' => [['value' => ['messages' => [['id' => 'x', 'from' => '263', 'text' => ['body' => 'x']]]]]]]]];

        $this->call('POST', '/integrations/whatsapp/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256=notreal',
        ], json_encode($body))->assertForbidden();
    }
}
