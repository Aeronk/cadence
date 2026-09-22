<?php

namespace App\Http\Controllers\Integrations;

use App\Http\Controllers\Controller;
use App\Integrations\IntegrationManager;
use App\Jobs\SyncIntegrationAccountCalendar;
use App\Models\CalendarSource;
use App\Models\IntegrationAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Choosing which of an account's calendars Cadence reads, and which one it
 * writes meetings to.
 */
class CalendarSourceController extends Controller
{
    public function __construct(protected IntegrationManager $manager) {}

    /** Re-read the account's calendars from the provider. */
    public function refresh(Request $request, IntegrationAccount $account): RedirectResponse
    {
        $this->authorizeAccount($request, $account);

        try {
            $count = $this->manager->calendar($account)->syncCalendarList($account);
        } catch (Throwable $e) {
            $account->forceFill(['last_error' => $e->getMessage()])->save();

            return back()->with('flash.error', 'Could not read your calendars: '.$e->getMessage());
        }

        return back()->with('flash.success', "Found {$count} calendar(s).");
    }

    public function update(Request $request, CalendarSource $source): RedirectResponse
    {
        $this->authorizeAccount($request, $source->integrationAccount);

        $data = $request->validate([
            'is_selected' => ['sometimes', 'boolean'],
            'is_write_target' => ['sometimes', 'boolean'],
        ]);

        if (($data['is_write_target'] ?? false) === true) {
            if (! $source->isWritable()) {
                return back()->with(
                    'flash.error',
                    "You only have read access to \"{$source->name}\", so meetings cannot be created there.",
                );
            }

            // Exactly one write target per account, or a push has to guess.
            CalendarSource::query()
                ->where('integration_account_id', $source->integration_account_id)
                ->whereKeyNot($source->id)
                ->update(['is_write_target' => false]);

            // Meetings have to land somewhere Cadence is also reading back, or
            // the event it creates never returns on the next sync.
            $source->is_selected = true;
        }

        $source->fill($data)->save();

        // A calendar just switched on has no events yet; waiting up to fifteen
        // minutes for the poll makes the toggle look broken.
        if (($data['is_selected'] ?? false) === true) {
            SyncIntegrationAccountCalendar::dispatch($source->integration_account_id);
        }

        return back()->with('flash.success', 'Calendar updated.');
    }

    protected function authorizeAccount(Request $request, ?IntegrationAccount $account): void
    {
        abort_unless($account !== null && $account->user_id === $request->user()->id, 403);
    }
}
