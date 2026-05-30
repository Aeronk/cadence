<?php

namespace App\Http\Controllers\Calendar;

use App\Http\Controllers\Controller;
use App\Models\CalendarEvent;
use App\Models\Meeting;
use App\Models\PersonalEvent;
use App\Models\Trip;
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

        $view = $request->string('view')->toString() ?: 'month';
        if (! in_array($view, ['day', 'week', 'month'], true)) {
            $view = 'month';
        }

        $cursor = $this->parseCursor($request->string('date')->toString(), $view);
        [$rangeStart, $rangeEnd, $label, $prev, $next] = $this->rangeFor($view, $cursor);

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
            ->get(['id', 'title', 'starts_at', 'ends_at', 'location', 'meeting_url', 'meeting_type'])
            ->map(fn ($m) => [
                'id' => 'meeting-'.$m->id,
                'source' => 'cadence',
                'title' => $m->title,
                'starts_at' => $m->starts_at->toIso8601String(),
                'ends_at' => $m->ends_at->toIso8601String(),
                'url' => route('meetings.show', $m->id),
                'meta' => $m->location ?: $m->meeting_url,
                'meeting_type' => $m->meeting_type,
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
                'meeting_type' => null,
            ]);

        // Travel days — all dates between departs_at and returns_at for any
        // trip belonging to the user that intersects the visible range.
        $tripDays = [];
        Trip::query()
            ->forWorkspace($workspace)
            ->where('user_id', $user->id)
            ->intersectingRange($rangeStart, $rangeEnd)
            ->whereNotIn('status', [Trip::STATUS_CANCELLED])
            ->get(['id', 'name', 'destination_city', 'departs_at', 'returns_at'])
            ->each(function ($trip) use (&$tripDays, $rangeStart, $rangeEnd) {
                $cursor = $trip->departs_at->startOfDay();
                $end = $trip->returns_at->startOfDay();
                while ($cursor->lessThanOrEqualTo($end)) {
                    if ($cursor->greaterThanOrEqualTo($rangeStart) && $cursor->lessThanOrEqualTo($rangeEnd)) {
                        $tripDays[] = [
                            'date' => $cursor->toDateString(),
                            'trip_id' => $trip->id,
                            'trip_name' => $trip->name,
                            'destination' => $trip->destination_city,
                        ];
                    }
                    $cursor = $cursor->addDay();
                }
            });

        // Personal events overlay — birthdays, anniversaries (yearly recurring)
        $personalDays = [];
        PersonalEvent::query()
            ->where('user_id', $user->id)
            ->get(['id', 'title', 'category', 'event_date', 'recurs_yearly'])
            ->each(function (PersonalEvent $ev) use (&$personalDays, $rangeStart, $rangeEnd) {
                foreach ($ev->occurrencesIn($rangeStart, $rangeEnd) as $iso) {
                    $personalDays[] = [
                        'id' => $ev->id,
                        'date' => $iso,
                        'title' => $ev->title,
                        'category' => $ev->category,
                    ];
                }
            });

        return Inertia::render('Calendar/Index', [
            'view' => $view,
            'cursor_iso' => $cursor->toIso8601String(),
            'cursor_label' => $label,
            'prev_cursor' => $prev,
            'next_cursor' => $next,
            'today_iso' => now()->toDateString(),
            'events' => $meetings->concat($external)->values(),
            'travel_days' => $tripDays,
            'personal_events' => $personalDays,
        ]);
    }

    protected function parseCursor(?string $input, string $view): CarbonImmutable
    {
        try {
            $base = $input ? CarbonImmutable::parse($input) : CarbonImmutable::now();
        } catch (\Throwable) {
            $base = CarbonImmutable::now();
        }

        return match ($view) {
            'day' => $base->startOfDay(),
            'week' => $base->startOfWeek(CarbonImmutable::SUNDAY),
            default => $base->startOfMonth(),
        };
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: string, 3: string, 4: string}
     */
    protected function rangeFor(string $view, CarbonImmutable $cursor): array
    {
        return match ($view) {
            'day' => [
                $cursor,
                $cursor->endOfDay(),
                $cursor->format('l, F j, Y'),
                $cursor->subDay()->toDateString(),
                $cursor->addDay()->toDateString(),
            ],
            'week' => [
                $cursor,
                $cursor->endOfWeek(CarbonImmutable::SATURDAY),
                $cursor->format('M j').' – '.$cursor->endOfWeek(CarbonImmutable::SATURDAY)->format('M j, Y'),
                $cursor->subWeek()->toDateString(),
                $cursor->addWeek()->toDateString(),
            ],
            default => [
                $cursor->startOfMonth()->startOfWeek(CarbonImmutable::SUNDAY),
                $cursor->endOfMonth()->endOfWeek(CarbonImmutable::SATURDAY),
                $cursor->format('F Y'),
                $cursor->subMonth()->toDateString(),
                $cursor->addMonth()->toDateString(),
            ],
        };
    }
}
