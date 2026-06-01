<?php

namespace App\Http\Controllers\Notifications;

use App\Http\Controllers\Controller;
use App\Notifications\TestNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $last = $user->notifications()->latest()->first();

        return Inertia::render('Notifications/Index', [
            'notifications' => $user->notifications()->limit(50)->get(),
            'unread_count' => $user->unreadNotifications()->count(),
            'diagnostics' => [
                'total' => $user->notifications()->count(),
                'last_at' => $last?->created_at?->toIso8601String(),
                'realtime_key_set' => (bool) config('broadcasting.connections.reverb.key'),
                'broadcaster' => (string) config('broadcasting.default'),
            ],
        ]);
    }

    public function markRead(Request $request, string $id): RedirectResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return back();
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back();
    }

    public function test(Request $request): RedirectResponse
    {
        $request->user()->notify(new TestNotification(
            "Hi {$request->user()->name}, this is a self-sent test fired at "
            . now()->format('H:i:s') . '. If you can see it in the list and the bell badge updated, the database channel works.'
        ));

        return back()->with('flash.success', 'Test notification sent.');
    }
}
