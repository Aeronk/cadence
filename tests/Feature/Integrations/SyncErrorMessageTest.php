<?php

namespace Tests\Feature\Integrations;

use App\Enums\IntegrationProvider;
use App\Integrations\IntegrationManager;
use App\Integrations\Providers\Gmail\GmailProvider;
use App\Integrations\SyncErrorMessage;
use App\Models\CalendarSource;
use App\Models\IntegrationAccount;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use PDOException;
use Tests\TestCase;

/**
 * What a user is allowed to see when a sync fails.
 *
 * A database error prints the whole statement — table and column names, the
 * host, the database name, and every value being written, which on a calendar
 * sync is the content of somebody's diary. That was going into `last_error` and
 * straight onto the integrations page.
 */
class SyncErrorMessageTest extends TestCase
{
    use RefreshDatabase;

    protected function queryException(): QueryException
    {
        return new QueryException(
            'mysql',
            'insert into `calendar_events` (`title`, `starts_at`) values (?, ?)',
            ['Lunch with the auditor', '2038-05-29 00:00:00'],
            new PDOException("SQLSTATE[22007]: Incorrect datetime value: '2038-05-29 00:00:00' for column 'starts_at'"),
        );
    }

    public function test_a_database_error_never_reaches_the_user(): void
    {
        $message = SyncErrorMessage::describe($this->queryException());

        foreach (['calendar_events', 'starts_at', 'insert into', 'mysql', 'Lunch with the auditor'] as $leak) {
            $this->assertStringNotContainsString($leak, $message);
        }

        $this->assertSame('Could not save the synced data. The problem has been logged.', $message);
    }

    public function test_provider_errors_say_what_to_do_about_them(): void
    {
        $cases = [
            401 => 'Reconnect the account',
            404 => 'could not find that calendar',
            429 => 'rate limiting',
            503 => 'having trouble',
        ];

        // A distinct path per case: repeated Http::fake() calls accumulate
        // stubs and the first matching pattern wins, so reusing one URL would
        // quietly test the same response four times.
        foreach ($cases as $status => $expected) {
            Http::fake(["example.test/{$status}" => Http::response(['error' => 'x'], $status)]);

            try {
                Http::get("https://example.test/{$status}")->throw();
                $this->fail("Expected a {$status} to throw");
            } catch (RequestException $e) {
                $this->assertSame($status, $e->response->status());
                $this->assertStringContainsString($expected, SyncErrorMessage::describe($e));
            }
        }
    }

    public function test_throttling_reads_the_reason_not_just_the_status(): void
    {
        // Gmail reports throttling as a 403, which otherwise looks identical to
        // a permissions refusal and would tell the user to reconnect for no
        // reason.
        Http::fake(['example.test/*' => Http::response([
            'error' => [
                'message' => "Quota exceeded for quota metric 'Total Query Cost'",
                'errors' => [['reason' => 'rateLimitExceeded']],
            ],
        ], 403)]);

        try {
            Http::get('https://example.test/thing')->throw();
        } catch (RequestException $e) {
            $this->assertStringContainsString('rate limiting', SyncErrorMessage::describe($e));
        }
    }

    public function test_a_plain_403_still_tells_you_to_reconnect(): void
    {
        Http::fake(['example.test/*' => Http::response([
            'error' => ['message' => 'Insufficient Permission', 'errors' => [['reason' => 'insufficientPermissions']]],
        ], 403)]);

        try {
            Http::get('https://example.test/thing')->throw();
        } catch (RequestException $e) {
            $this->assertStringContainsString('Reconnect the account', SyncErrorMessage::describe($e));
        }
    }

    public function test_an_unreachable_provider_is_described_as_temporary(): void
    {
        $message = SyncErrorMessage::describe(new ConnectionException('cURL error 28: timed out'));

        $this->assertStringNotContainsString('cURL', $message);
        $this->assertStringContainsString('clears on its own', $message);
    }

    public function test_the_sync_now_button_shows_the_safe_message_not_the_query(): void
    {
        app(IntegrationManager::class)->bind(IntegrationProvider::Gmail, GmailProvider::class);

        $user = User::factory()->create();
        $account = IntegrationAccount::factory()
            ->provider(IntegrationProvider::Gmail)
            ->create(['user_id' => $user->id, 'workspace_id' => $user->currentWorkspace()->id]);

        CalendarSource::create([
            'integration_account_id' => $account->id,
            'workspace_id' => $account->workspace_id,
            'external_id' => 'primary',
            'name' => 'Personal',
            'is_selected' => true,
        ]);

        Http::fake([
            'googleapis.com/calendar/v3/users/me/calendarList*' => Http::response(
                ['error' => ['message' => 'Insufficient Permission']],
                403,
            ),
        ]);

        $this->actingAs($user)
            ->post(route('integrations.calendars.refresh', $account))
            ->assertRedirect();

        $stored = $account->fresh()->last_error;

        $this->assertStringNotContainsString('googleapis.com', $stored);
        $this->assertStringContainsString('Reconnect the account', $stored);
    }
}
