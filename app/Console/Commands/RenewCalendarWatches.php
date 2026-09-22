<?php

namespace App\Console\Commands;

use App\Enums\IntegrationProvider;
use App\Integrations\IntegrationManager;
use App\Models\CalendarSource;
use Illuminate\Console\Command;
use Throwable;

/**
 * Re-register the push channels that tell Cadence a calendar changed.
 *
 * These expire — Google caps a calendar channel at about a month, Graph at
 * roughly three days — so without this they lapse and the app silently falls
 * back to the 15-minute poll. Nothing was renewing them before; nothing was
 * registering them either.
 */
class RenewCalendarWatches extends Command
{
    protected $signature = 'integrations:renew-calendar-watches
                            {--account= : Only this integration account}
                            {--force : Renew even if the current channel has plenty of life left}';

    protected $description = 'Register or renew calendar push notification channels before they expire.';

    /** Providers that implement CalendarProvider. */
    protected const CALENDAR_PROVIDERS = [
        IntegrationProvider::Gmail,
        IntegrationProvider::Microsoft,
    ];

    public function handle(IntegrationManager $manager): int
    {
        $sources = CalendarSource::query()
            ->selected()
            ->whereHas('integrationAccount', function ($q) {
                $q->where('status', 'active')->whereIn(
                    'provider',
                    array_map(fn (IntegrationProvider $p) => $p->value, self::CALENDAR_PROVIDERS),
                );
            })
            ->when($this->option('account'), fn ($q, $id) => $q->where('integration_account_id', (int) $id))
            ->with('integrationAccount')
            ->get();

        $renewed = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($sources as $source) {
            // A channel with hours left is renewed early on purpose: waiting for
            // it to lapse leaves a window where changes arrive nowhere.
            if (! $this->option('force') && ! $source->needsWatchRenewal(graceMinutes: 720)) {
                $skipped++;

                continue;
            }

            try {
                $manager->calendar($source->integrationAccount)
                    ->watchCalendar($source->integrationAccount, $source);

                $renewed++;
            } catch (Throwable $e) {
                // One calendar's revoked access must not stop the rest renewing.
                $source->forceFill(['last_error' => $e->getMessage()])->save();
                $this->warn("Calendar {$source->name} (#{$source->id}): {$e->getMessage()}");
                $failed++;
            }
        }

        $this->info("Renewed {$renewed}, still current {$skipped}, failed {$failed}.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
