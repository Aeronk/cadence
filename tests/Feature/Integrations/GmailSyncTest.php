<?php

namespace Tests\Feature\Integrations;

use App\Enums\IntegrationProvider;
use App\Integrations\IntegrationManager;
use App\Integrations\Providers\Gmail\GmailProvider;
use App\Jobs\SyncIntegrationAccountInbox;
use App\Models\IntegrationAccount;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GmailSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(IntegrationManager::class)->bind(IntegrationProvider::Gmail, GmailProvider::class);
    }

    public function test_sync_persists_inbound_messages(): void
    {
        Http::fake([
            'gmail.googleapis.com/gmail/v1/users/me/messages?*' => Http::response([
                'messages' => [['id' => 'msg-1'], ['id' => 'msg-2']],
            ]),
            'gmail.googleapis.com/gmail/v1/users/me/messages/msg-1*' => Http::response([
                'id' => 'msg-1',
                'snippet' => 'Hello world',
                'payload' => ['headers' => [
                    ['name' => 'From', 'value' => 'alice@example.com'],
                    ['name' => 'To', 'value' => 'bob@example.com'],
                    ['name' => 'Subject', 'value' => 'Greetings'],
                ]],
            ]),
            'gmail.googleapis.com/gmail/v1/users/me/messages/msg-2*' => Http::response([
                'id' => 'msg-2',
                'snippet' => 'Second',
                'payload' => ['headers' => [
                    ['name' => 'From', 'value' => 'carol@example.com'],
                    ['name' => 'To', 'value' => 'bob@example.com'],
                    ['name' => 'Subject', 'value' => 'Hi'],
                ]],
            ]),
        ]);

        $account = IntegrationAccount::factory()
            ->provider(IntegrationProvider::Gmail)
            ->create();

        SyncIntegrationAccountInbox::dispatchSync($account->id);

        $this->assertSame(2, Message::query()->where('integration_account_id', $account->id)->count());
        $this->assertNotNull($account->fresh()->last_synced_at);
    }

    public function test_sync_is_idempotent_on_repeat(): void
    {
        Http::fake([
            'gmail.googleapis.com/gmail/v1/users/me/messages?*' => Http::response([
                'messages' => [['id' => 'msg-1']],
            ]),
            'gmail.googleapis.com/gmail/v1/users/me/messages/msg-1*' => Http::response([
                'id' => 'msg-1',
                'snippet' => 'Once',
                'payload' => ['headers' => [['name' => 'From', 'value' => 'a@b.com']]],
            ]),
        ]);

        $account = IntegrationAccount::factory()->provider(IntegrationProvider::Gmail)->create();

        SyncIntegrationAccountInbox::dispatchSync($account->id);
        SyncIntegrationAccountInbox::dispatchSync($account->id);

        $this->assertSame(1, Message::query()->where('external_id', 'msg-1')->count());
    }

    public function test_sync_records_last_error_on_failure(): void
    {
        Http::fake([
            'gmail.googleapis.com/*' => Http::response('Server Error', 500),
        ]);

        $account = IntegrationAccount::factory()->provider(IntegrationProvider::Gmail)->create();

        try {
            SyncIntegrationAccountInbox::dispatchSync($account->id);
            $this->fail('Expected sync to throw.');
        } catch (\Throwable) {
            $this->assertNotNull($account->fresh()->last_error);
        }
    }
}
