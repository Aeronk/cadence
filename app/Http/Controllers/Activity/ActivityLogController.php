<?php

namespace App\Http\Controllers\Activity;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ActivityLogController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $workspace = $user->currentWorkspace();
        abort_unless($workspace !== null, 404);

        $activity = ActivityLog::query()
            ->forWorkspace($workspace)
            ->with(['actor', 'subject'])
            ->latest()
            ->limit(100)
            ->get();

        return Inertia::render('Activity/Index', ['activity' => $activity]);
    }
}
