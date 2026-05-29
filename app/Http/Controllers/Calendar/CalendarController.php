<?php

namespace App\Http\Controllers\Calendar;

use App\Http\Controllers\Controller;
use App\Models\CalendarEvent;
use App\Models\Meeting;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CalendarController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $workspace = $user->currentWorkspace();
        abort_unless($workspace !== null, 404);

        $cursor = $this->parseMonth($request->string('month')->toString());

        $rangeStart = $cursor->startOfMonth()->startOfWeek(CarbonImmutable::SUNDAY);
        $rangeEnd = $cursor->endOfMonth()->endOfWeek(CarbonImmutable::SATURDAY);

        $meetings = Meeting::query()
            ->forWorkspace($workspace)
            ->whereBetween('starts_at', [$rangeStart, $rangeEnd])
            ->where(function ($q) use ($user, $workspace) {
                if ($workspace->roleFor($user)?->canManageWorkspace()) {
                    return;
                }
                $q->where('host_id', $user->id)
                    ->orWhereHas('attendees', fn ($q) => $q->where('users.id', $user->id));
            })
            ->orderBy('starts_at')
            ->get(['id', 'title', 'starts_at', 'ends_at', 'location'])
            ->map(fn ($m) => [
                'id' => 'meeting-'.$m->id,
                'source' => 'cadence',
                'title' => $m->title,
                'starts_at' => $m->starts_at->toIso8601String(),
                'ends_at' => $m->ends_at->toIso8601String(),
                'url' => route('meetings.show', $m->id),
                'meta' => $m->location,
            ]);

        $external = CalendarEvent::query()
            ->forWorkspace($workspace)
            ->whereHas('integrationAccount', fn ($q) => $q->where('user_id', $user->id))
            ->whereBetween('starts_at', [$rangeStart, $rangeEnd])
            ->orderBy('starts_at')
            ->get(['id', 'title', 'starts_at', 'ends_at', 'location', 'integration_account_id'])
            ->map(fn ($e) => [
                'id' => 'external-'.$e->id,
                'source' => 'external',
                'title' => $e->title,
                'starts_at' => $e->starts_at?->toIso8601String(),
                'ends_at' => $e->ends_at?->toIso8601String(),
                'url' => null,
                'meta' => $e->location,
            ]);

        return Inertia::render('Calendar/Index', [
            'cursor_iso' => $cursor->startOfMonth()->toIso8601String(),
            'cursor_label' => $cursor->format('F Y'),
            'prev_month' => $cursor->subMonth()->format('Y-m'),
            'next_month' => $cursor->addMonth()->format('Y-m'),
            'today_iso' => now()->toDateString(),
            'events' => $meetings->concat($external)->values(),
        ]);
    }

    protected function parseMonth(?string $input): CarbonImmutable
    {
        try {
            return $input ? CarbonImmutable::parse($input.'-01') : CarbonImmutable::now()->startOfMonth();
        } catch (\Throwable) {
            return CarbonImmutable::now()->startOfMonth();
        }
    }
}
