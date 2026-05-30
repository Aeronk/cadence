<?php

namespace Tests\Feature\Integrations;

use App\Enums\IntegrationProvider;
use App\Enums\MessageChannel;
use App\Integrations\IntegrationManager;
use App\Integrations\Providers\Twilio\TwilioProvider;
use App\Models\IntegrationAccount;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TwilioSmsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(IntegrationManager::class)->bind(IntegrationProvider::TwilioSms, TwilioProvider::class);

        config()->set('integrations.twilio.account_sid', 'ACtest');
        config()->set('integrations.twilio.auth_token', 'test-token');
        config()->set('integrations.twilio.from_number', '+15555550000');
    }

    public function test_send_posts_to_twilio_and_persists_message(): void
    {
        Http::fake([
            'api.twilio.com/2010-04-01/Accounts/*/Messages.json' => Http::response([
                'sid' => 'SM123',
                'status' => 'queued',
            ]),
        ]);

        $account = IntegrationAccount::factory()->provider(IntegrationProvider::TwilioSms)->create();

        $provider = app(IntegrationManager::class)->messaging($account);
        $message = $provider->send($account, [
            'to' => '+15555551234',
            'body_text' => 'hello world',
        ]);

        $this->assertSame(MessageChannel::Sms, $message->channel);
        $this->assertSame(Message::DIRECTION_OUTBOUND, $message->direction);
        $this->assertSame('SM123', $message->external_id);

        Http::assertSent(fn ($req) => $req->data()['To'] === '+15555551234'
            && $req->data()['Body'] === 'hello world');
    }

    public function test_webhook_rejects_bad_signature(): void
    {
        IntegrationAccount::factory()->provider(IntegrationProvider::TwilioSms)->create();

        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
            ->post('/integrations/twilio/webhook', [
                'MessageSid' => 'SMtest',
                'From' => '+15555551234',
                'To' => '+15555550000',
                'Body' => 'inbound',
            ], ['X-Twilio-Signature' => 'invalid'])
            ->assertForbidden();
    }

    public function test_webhook_persists_inbound_message_with_valid_signature(): void
    {
        IntegrationAccount::factory()->provider(IntegrationProvider::TwilioSms)->create();

        $url = url('/integrations/twilio/webhook');
        $params = ['Body' => 'inbound', 'From' => '+15555551234', 'MessageSid' => 'SMinbound', 'To' => '+15555550000'];
        ksort($params);
        $data = $url;
        foreach ($params as $k => $v) {
            $data .= $k.$v;
        }
        $signature = base64_encode(hash_hmac('sha1', $data, 'test-token', true));

        $this->post('/integrations/twilio/webhook', $params, ['X-Twilio-Signature' => $signature])
            ->assertOk();

        $this->assertDatabaseHas('messages', [
            'channel' => MessageChannel::Sms->value,
            'direction' => Message::DIRECTION_INBOUND,
            'external_id' => 'SMinbound',
            'body_text' => 'inbound',
        ]);
    }

    public function test_webhook_inbound_is_idempotent(): void
    {
        IntegrationAccount::factory()->provider(IntegrationProvider::TwilioSms)->create();

        $url = url('/integrations/twilio/webhook');
        $params = ['Body' => 'dup', 'From' => '+1', 'MessageSid' => 'SMdup', 'To' => '+2'];
        ksort($params);
        $data = $url;
        foreach ($params as $k => $v) {
            $data .= $k.$v;
        }
        $sig = base64_encode(hash_hmac('sha1', $data, 'test-token', true));

        $this->post('/integrations/twilio/webhook', $params, ['X-Twilio-Signature' => $sig])->assertOk();
        $this->post('/integrations/twilio/webhook', $params, ['X-Twilio-Signature' => $sig])->assertOk();

        $this->assertSame(1, Message::query()->where('external_id', 'SMdup')->count());
    }
}
