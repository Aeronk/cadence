<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = $request->user();
        $currentWorkspace = $user?->currentWorkspace();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
                'workspaces' => fn () => $user
                    ? $user->workspaces()
                        ->select('workspaces.id', 'workspaces.name', 'workspaces.slug', 'workspaces.is_personal')
                        ->get()
                    : [],
                'currentWorkspace' => $currentWorkspace
                    ? [
                        'id' => $currentWorkspace->id,
                        'name' => $currentWorkspace->name,
                        'slug' => $currentWorkspace->slug,
                        'is_personal' => $currentWorkspace->is_personal,
                        'role' => $currentWorkspace->roleFor($user)?->value,
                    ]
                    : null,
                'unreadNotificationsCount' => fn () => $user
                    ? $user->unreadNotifications()->count()
                    : 0,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('flash.success'),
                'error' => fn () => $request->session()->get('flash.error'),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
