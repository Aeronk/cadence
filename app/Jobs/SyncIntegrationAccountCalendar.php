<?php

namespace App\Jobs;

use App\Integrations\IntegrationManager;
use App\Models\IntegrationAccount;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SyncIntegrationAccountCalendar implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public int $integrationAccountId,
        /**
         * Re-read which calendars the account has before pulling events. Off for
         * the routine poll, which would otherwise spend a request per account
         * per quarter-hour re-learning a list that rarely changes.
         */
        public bool $refreshCalendarList = false,
    ) {}

    public function handle(IntegrationManager $manager): void
    {
        $account = IntegrationAccount::query()->find($this->integrationAccountId);

        if (! $account || ! $account->isActive()) {
            return;
        }

        try {
            $provider = $manager->calendar($account);

            if ($this->refreshCalendarList) {
                $provider->syncCalendarList($account);
            }

            $provider->syncEvents($account);
        } catch (Throwable $e) {
            $account->forceFill(['last_error' => $e->getMessage()])->save();
            throw $e;
        }
    }
}
