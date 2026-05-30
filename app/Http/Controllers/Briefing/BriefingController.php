<?php

namespace App\Http\Controllers\Briefing;

use App\Http\Controllers\Controller;
use App\Models\DailyBriefing;
use App\Services\AI\BriefingComposer;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BriefingController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $workspace = $user->currentWorkspace();
        abort_unless($workspace !== null, 404);

        $today = CarbonImmutable::now()->toDateString();
        $briefing = DailyBriefing::query()
            ->where('user_id', $user->id)
            ->where('workspace_id', $workspace->id)
            ->where('briefing_date', $today)
            ->first();

        $recent = DailyBriefing::query()
            ->where('user_id', $user->id)
            ->where('workspace_id', $workspace->id)
            ->orderByDesc('briefing_date')
            ->limit(7)
            ->get(['id', 'briefing_date', 'summary']);

        return Inertia::render('Briefing/Index', [
            'today' => $today,
            'briefing' => $briefing,
            'recent' => $recent,
        ]);
    }

    public function regenerate(Request $request, BriefingComposer $composer): RedirectResponse
    {
        $user = $request->user();
        $workspace = $user->currentWorkspace();
        abort_unless($workspace !== null, 404);

        $composer->composeFor($user, $workspace);
        return back();
    }
}
