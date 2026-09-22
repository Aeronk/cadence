<?php

namespace App\Http\Controllers\Integrations;

use App\Enums\IntegrationProvider;
use App\Http\Controllers\Controller;
use App\Models\CalendarSource;
use App\Models\IntegrationAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IntegrationAccountController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $accounts = IntegrationAccount::query()
            ->where('user_id', $user->id)
            ->with(['calendarSources' => fn ($q) => $q->orderByDesc('is_primary')->orderBy('name')])
            ->get()
            ->map(fn (IntegrationAccount $a) => [
                'id' => $a->id,
                'provider' => $a->provider->value,
                'provider_label' => $a->provider->label(),
                'display_name' => $a->display_name,
                'status' => $a->status,
                'last_synced_at' => $a->last_synced_at?->toIso8601String(),
                'last_error' => $a->last_error,
                'token_expired' => $a->tokenIsExpired(),
                // Only the calendar providers have calendars to choose between;
                // for the rest this is an empty list and the section is hidden.
                'calendars' => $a->calendarSources->map(fn (CalendarSource $c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'color' => $c->color,
                    'is_primary' => $c->is_primary,
                    'is_selected' => $c->is_selected,
                    'is_write_target' => $c->is_write_target,
                    'is_writable' => $c->isWritable(),
                    'access_role' => $c->access_role,
                    'last_synced_at' => $c->last_synced_at?->toIso8601String(),
                    'last_error' => $c->last_error,
                    // Push is what makes a change show up in seconds rather than
                    // at the next quarter-hour poll.
                    'push_active' => $c->watch_expires_at !== null && $c->watch_expires_at->isFuture(),
                ])->values(),
            ]);

        return Inertia::render('settings/Integrations', [
            'accounts' => $accounts,
            'available_providers' => collect(IntegrationProvider::cases())->map(fn ($p) => [
                'value' => $p->value,
                'label' => $p->label(),
                'channel' => $p->channel()->value,
                // Only a provider with its own OAuth flow can be clicked. The
                // rest are either covered by another connection or configured
                // in the environment, and used to render as links that 404'd.
                'connectable' => $p->isConnectable() && $p->credentialsConfigured(),
                'unavailable_reason' => $p->unavailableReason(),
            ]),
        ]);
    }

    public function destroy(Request $request, IntegrationAccount $account): RedirectResponse
    {
        abort_unless($account->user_id === $request->user()->id, 403);

        $account->delete();

        return back()->with('flash.success', 'Integration disconnected.');
    }
}
