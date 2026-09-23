<?php

namespace App\Integrations;

use Illuminate\Database\QueryException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Turns a sync failure into something safe to show someone.
 *
 * Raw exception messages were being written to `last_error` and rendered on the
 * integrations page. A database failure there prints the full statement — table
 * and column names, the host, the database name, and every value being written,
 * which on a calendar sync is the content of somebody's diary. That is an
 * information disclosure as much as it is bad copy.
 *
 * The detail is not lost: it goes to the log, where operators can reach it and
 * users cannot.
 */
final class SyncErrorMessage
{
    /**
     * A short, safe description of what went wrong.
     *
     * @param  array<string, mixed>  $context  Extra detail for the log only.
     */
    public static function describe(Throwable $e, array $context = []): string
    {
        Log::error('Integration sync failed: '.$e->getMessage(), $context + [
            'exception' => $e::class,
        ]);

        return match (true) {
            // Never surfaced verbatim: the message carries the whole statement.
            $e instanceof QueryException => 'Could not save the synced data. The problem has been logged.',

            $e instanceof ConnectionException => 'Could not reach the provider. This usually clears on its own.',

            $e instanceof RequestException => self::fromResponse($e),

            default => 'Something went wrong during sync. The problem has been logged.',
        };
    }

    /**
     * Provider errors are worth distinguishing, because what the user should do
     * about them differs — reconnecting, waiting, or nothing at all.
     */
    private static function fromResponse(RequestException $e): string
    {
        $status = $e->response->status();

        return match (true) {
            $status === 401 => 'The connection has expired. Reconnect the account to continue syncing.',
            $status === 403 && self::isRateLimit($e) => 'The provider is rate limiting us. Syncing will catch up shortly.',
            $status === 403 => 'The provider refused access. Reconnect the account and make sure calendar access is granted.',
            $status === 404 => 'The provider could not find that calendar. It may have been deleted or unshared.',
            $status === 429 => 'The provider is rate limiting us. Syncing will catch up shortly.',
            $status >= 500 => 'The provider is having trouble. Syncing will retry automatically.',
            default => 'The provider rejected the request. The problem has been logged.',
        };
    }

    private static function isRateLimit(RequestException $e): bool
    {
        $reason = $e->response->json('error.errors.0.reason', '');
        $message = (string) $e->response->json('error.message', '');

        return in_array($reason, ['rateLimitExceeded', 'userRateLimitExceeded'], true)
            || str_contains($message, 'Quota exceeded');
    }
}
