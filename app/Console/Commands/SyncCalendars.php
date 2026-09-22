<?php

namespace App\Console\Commands;

use App\Enums\IntegrationProvider;
use App\Jobs\SyncIntegrationAccountCalendar;
use App\Models\IntegrationAccount;
use Illuminate\Console\Command;

class SyncCalendars extends Command
{
    protected $signature = 'integrations:sync-calendars
                            {--account= : Sync a single integration account by id}';

    protected $description = 'Pull calendar events for every connected account.';

    /**
     * Providers that implement CalendarProvider. Accounts on other providers are
     * skipped rather than dispatched and thrown away inside the job.
     */
    protected const CALENDAR_PROVIDERS = [
        IntegrationProvider::Gmail,
        IntegrationProvider::Microsoft,
    ];

    public function handle(): int
    {
        $accounts = IntegrationAccount::query()
            ->where('status', 'active')
            ->whereIn(
                'provider',
                array_map(fn (IntegrationProvider $p) => $p->value, self::CALENDAR_PROVIDERS),
            )
            ->when($this->option('account'), fn ($query, $id) => $query->whereKey((int) $id))
            ->get(['id', 'provider', 'display_name']);

        if ($accounts->isEmpty()) {
            $this->info('No connected calendar accounts.');

            return self::SUCCESS;
        }

        foreach ($accounts as $account) {
            // Queued per account so one failing token cannot stall the rest.
            SyncIntegrationAccountCalendar::dispatch($account->id);
        }

        $this->info("Queued calendar sync for {$accounts->count()} account(s).");

        return self::SUCCESS;
    }
}
