<?php

namespace App\Http\Controllers\Integrations;

use App\Enums\IntegrationProvider;
use App\Http\Controllers\Controller;
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
            ->get(['id', 'provider', 'display_name', 'status', 'last_synced_at', 'last_error', 'token_expires_at'])
            ->map(fn ($a) => [
                'id' => $a->id,
                'provider' => $a->provider->value,
                'provider_label' => $a->provider->label(),
                'display_name' => $a->display_name,
                'status' => $a->status,
                'last_synced_at' => $a->last_synced_at?->toIso8601String(),
                'last_error' => $a->last_error,
                'token_expired' => $a->tokenIsExpired(),
            ]);

        return Inertia::render('settings/Integrations', [
            'accounts' => $accounts,
            'available_providers' => collect(IntegrationProvider::cases())->map(fn ($p) => [
                'value' => $p->value,
                'label' => $p->label(),
                'channel' => $p->channel()->value,
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
