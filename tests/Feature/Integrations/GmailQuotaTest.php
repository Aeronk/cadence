<?php

namespace Tests\Feature\Integrations;

use App\Enums\IntegrationProvider;
use App\Enums\MessageChannel;
use App\Integrations\Providers\Gmail\GmailProvider;
use App\Models\IntegrationAccount;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Gmail charges quota per call against roughly 250 units per user per second.
 * The first sync of a real mailbox used to walk every page and fetch every
 * message in a tight loop, which exhausted the budget in about a second and
 * failed the whole run with a 403.
 */
class GmailQuotaTest extends TestCase
{
    use RefreshDatabase;

    protected IntegrationAccount $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = IntegrationAccount::factory()
            ->provider(IntegrationProvider::Gmail)
            ->create();
    }

    protected function provider(): GmailProvider
    {
        return app(GmailProvider::class);
    }

    /**
     * A list page of `$count` message stubs, with a next-page token so a naive
     * implementation would keep going forever.
     */
    protected function listPage(int $count, int $offset = 0, ?string $next = 'more'): array
    {
        return [
            'messages' => collect(range(1, $count))
                ->map(fn ($i) => ['id' => 'msg-'.($offset + $i)])
                ->all(),
            'nextPageToken' => $next,
        ];
    }

    protected function messageDetail(string $id = 'msg-1'): array
    {
        return [
            'id' => $id,
            'snippet' => 'Preview text',
            'payload' => ['headers' => [
                ['name' => 'From', 'value' => 'sender@example.com'],
                ['name' => 'To', 'value' => 'me@example.com'],
                ['name' => 'Subject', 'value' => 'Hello'],
                ['name' => 'Date', 'value' => 'Tue, 15 Sep 2026 09:30:00 +0200'],
            ]],
        ];
    }

    public function test_a_first_sync_of_a_huge_mailbox_stops_instead_of_exhausting_the_quota(): void
    {
        $offset = 0;

        Http::fake([
            'gmail.googleapis.com/*/messages?*' => function () use (&$offset) {
                $page = $this->listPage(50, $offset);
                $offset += 50;

                return Http::response($page);
            },
            'gmail.googleapis.com/*/messages/*' => fn ($request) => Http::response(
                $this->messageDetail(basename(parse_url($request->url(), PHP_URL_PATH))),
            ),
        ]);

        $this->provider()->syncInbox($this->account);

        // Bounded: it takes a bite and leaves the rest for the next run, rather
        // than following nextPageToken until Google cuts it off.
        $this->assertSame(200, Message::count());
    }

    public function test_a_capped_run_does_not_advance_the_cursor_past_what_it_read(): void
    {
        $offset = 0;

        Http::fake([
            'gmail.googleapis.com/*/messages?*' => function () use (&$offset) {
                $page = $this->listPage(50, $offset);
                $offset += 50;

                return Http::response($page);
            },
            'gmail.googleapis.com/*/messages/*' => fn ($request) => Http::response(
                $this->messageDetail(basename(parse_url($request->url(), PHP_URL_PATH))),
            ),
        ]);

        $this->provider()->syncInbox($this->account);

        // Moving the cursor after a capped run would skip every message this run
        // never reached, and that mail would never be fetched at all.
        $this->assertNull($this->account->fresh()->sync_cursor);
    }

    public function test_the_cursor_advances_once_the_window_is_drained(): void
    {
        Http::fake([
            'gmail.googleapis.com/*/messages?*' => Http::response([
                'messages' => [['id' => 'msg-1']],
                // No nextPageToken: that was the lot.
            ]),
            'gmail.googleapis.com/*/messages/*' => Http::response($this->messageDetail()),
        ]);

        $this->provider()->syncInbox($this->account);

        $this->assertSame(now()->format('Y/m/d'), $this->account->fresh()->sync_cursor);
    }

    public function test_being_told_to_slow_down_is_retried_rather_than_failing_the_sync(): void
    {
        $attempts = 0;

        Http::fake([
            'gmail.googleapis.com/*/messages?*' => Http::response(['messages' => [['id' => 'msg-1']]]),
            'gmail.googleapis.com/*/messages/*' => function () use (&$attempts) {
                $attempts++;

                // Gmail reports throttling as a 403 with a rate-limit reason,
                // which is the exact shape that used to kill the whole run.
                if ($attempts === 1) {
                    return Http::response([
                        'error' => [
                            'code' => 403,
                            'message' => "Quota exceeded for quota metric 'Total Query Cost'",
                            'errors' => [['reason' => 'rateLimitExceeded']],
                        ],
                    ], 403);
                }

                return Http::response($this->messageDetail());
            },
        ]);

        $this->provider()->syncInbox($this->account);

        $this->assertSame(2, $attempts);
        $this->assertSame(1, Message::count());
    }

    public function test_a_genuine_permission_error_is_not_retried(): void
    {
        $attempts = 0;

        Http::fake([
            'gmail.googleapis.com/*/messages?*' => Http::response(['messages' => [['id' => 'msg-1']]]),
            'gmail.googleapis.com/*/messages/*' => function () use (&$attempts) {
                $attempts++;

                // A plain 403 means no permission. Retrying it just wastes more
                // quota and delays the real error.
                return Http::response([
                    'error' => ['code' => 403, 'message' => 'Insufficient Permission',
                        'errors' => [['reason' => 'insufficientPermissions']]],
                ], 403);
            },
        ]);

        $this->expectException(RequestException::class);

        try {
            $this->provider()->syncInbox($this->account);
        } finally {
            $this->assertSame(1, $attempts);
        }
    }

    public function test_a_message_keeps_the_date_it_was_actually_sent(): void
    {
        Http::fake([
            'gmail.googleapis.com/*/messages?*' => Http::response(['messages' => [['id' => 'msg-1']]]),
            'gmail.googleapis.com/*/messages/*' => Http::response($this->messageDetail()),
        ]);

        $this->provider()->syncInbox($this->account);

        // Every synced mail used to be stamped with the moment of the sync, so
        // a month of correspondence all landed at "just now".
        $this->assertSame(
            '2026-09-15 07:30:00',
            Message::first()->sent_at->utc()->format('Y-m-d H:i:s'),
        );
    }

    public function test_messages_already_stored_are_not_fetched_again(): void
    {
        Message::create([
            'workspace_id' => $this->account->workspace_id,
            'integration_account_id' => $this->account->id,
            'channel' => MessageChannel::Email->value,
            'direction' => Message::DIRECTION_INBOUND,
            'external_id' => 'msg-1',
            'subject' => 'Already here',
            'status' => 'received',
            'sent_at' => now(),
        ]);

        $detailCalls = 0;

        Http::fake([
            'gmail.googleapis.com/*/messages?*' => Http::response([
                'messages' => [['id' => 'msg-1'], ['id' => 'msg-2']],
            ]),
            'gmail.googleapis.com/*/messages/*' => function () use (&$detailCalls) {
                $detailCalls++;

                return Http::response($this->messageDetail('msg-2'));
            },
        ]);

        $this->provider()->syncInbox($this->account);

        // Re-fetching known mail is pure quota waste.
        $this->assertSame(1, $detailCalls);
        $this->assertSame(2, Message::count());
    }
}
