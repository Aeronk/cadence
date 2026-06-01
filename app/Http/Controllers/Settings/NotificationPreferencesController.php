<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationPreferencesController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('settings/Notifications', [
            'kinds' => User::NOTIFICATION_KINDS,
            'channels' => User::NOTIFICATION_CHANNELS,
            'preferences' => $this->resolvedPreferences($user),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'preferences' => ['required', 'array'],
            'preferences.*' => ['array'],
            'preferences.*.*' => ['boolean'],
        ]);

        $user = $request->user();
        $sanitized = [];
        foreach (User::NOTIFICATION_KINDS as $kind => $_) {
            foreach (User::NOTIFICATION_CHANNELS as $channel) {
                $sanitized[$kind][$channel] =
                    (bool) ($validated['preferences'][$kind][$channel] ?? false);
            }
        }

        $user->forceFill(['notification_preferences' => $sanitized])->save();

        return back()->with('flash.success', 'Notification preferences updated.');
    }

    protected function resolvedPreferences(User $user): array
    {
        $stored = $user->notification_preferences ?? [];
        $out = [];
        foreach (User::NOTIFICATION_KINDS as $kind => $_) {
            foreach (User::NOTIFICATION_CHANNELS as $channel) {
                $out[$kind][$channel] = $stored[$kind][$channel] ?? true;
            }
        }
        return $out;
    }
}
