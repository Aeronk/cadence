<?php

namespace App\Http\Controllers\Calendar;

use App\Http\Controllers\Controller;
use App\Models\CalendarEvent;
use App\Models\Meeting;
use App\Models\PersonalEvent;
use App\Models\Task;
use App\Models\Trip;
use App\Models\Workspace;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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

        // Order matters: meetings are the source of truth for anything Cadence
        // scheduled, and both the synced provider copy and the originating task
        // are suppressed against them so one commitment draws one row.
        $meetings = $this->meetings($workspace, $user, $rangeStart, $rangeEnd);
        $external = $this->externalEvents($workspace, $user, $rangeStart, $rangeEnd, $meetings);
        $tasks = $this->taskEvents($workspace, $user, $rangeStart, $rangeEnd);

        return Inertia::render('Calendar/Index', [
            'view' => $view,
            'cursor_iso' => $cursor->toIso8601String(),
            'cursor_label' => $label,
            'prev_cursor' => $prev,
            'next_cursor' => $next,
            'today_iso' => now()->toDateString(),
            'events' => $meetings->concat($external)->concat($tasks)->values(),
            'travel_days' => $this->travelDays($workspace, $user, $rangeStart, $rangeEnd),
            'personal_events' => $this->personalDays($user, $rangeStart, $rangeEnd),
        ]);
    }

    /**
     * Meetings the user can see in the window. A recurring meeting is stored once
     * and expanded by the provider, so nothing is fanned out here.
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected function meetings(
        Workspace $workspace,
        $user,
        CarbonImmutable $rangeStart,
        CarbonImmutable $rangeEnd,
    ): Collection {
        return Meeting::query()
            ->forWorkspace($workspace)
            ->whereBetween('starts_at', [$rangeStart, $rangeEnd])
            ->where(function ($q) use ($user, $workspace) {
                if ($workspace->roleFor($user)?->canManageWorkspace()) {
                    return;
                }
                $q->where('host_id', $user->id)
                    ->orWhereHas('attendees', fn ($q) => $q->where('users.id', $user->id));
            })
            ->with('task:id,meeting_id,title')
            ->orderBy('starts_at')
            ->get([
                'id', 'title', 'starts_at', 'ends_at', 'location', 'meeting_url',
                'meeting_type', 'external_event_id', 'recurrence_rule',
            ])
            ->map(fn (Meeting $m) => [
                'id' => 'meeting-'.$m->id,
                'source' => 'cadence',
                'title' => $m->title,
                'starts_at' => $m->starts_at->toIso8601String(),
                'ends_at' => $m->ends_at->toIso8601String(),
                'url' => route('meetings.show', $m->id),
                'meta' => $m->location ?: $m->meeting_url,
                'meeting_type' => $m->meeting_type,
                'all_day' => false,
                'recurring' => $m->recurrence_rule !== null && $m->recurrence_rule !== 'none',
                // Set when this meeting was scheduled from a task, so the UI can
                // point back at the task rather than looking like a stray event.
                'task_id' => $m->task?->id,
                'external_event_id' => $m->external_event_id,
            ]);
    }

    /**
     * Provider events, minus the ones that are just the provider's copy of a
     * meeting already drawn above.
     *
     * This is the fix for the same meeting appearing twice: pushing a meeting to
     * Google creates a calendar_events row pointing back at it, and the next sync
     * pulls that row in as though it were somebody else's event.
     *
     * @param  Collection<int, array<string, mixed>>  $meetings
     * @return Collection<int, array<string, mixed>>
     */
    protected function externalEvents(
        Workspace $workspace,
        $user,
        CarbonImmutable $rangeStart,
        CarbonImmutable $rangeEnd,
        Collection $meetings,
    ): Collection {
        // Belt and braces for rows written before meeting_id was linked: match on
        // the provider id the meeting itself recorded.
        $renderedExternalIds = $meetings
            ->pluck('external_event_id')
            ->filter()
            ->values()
            ->all();

        return CalendarEvent::query()
            ->forWorkspace($workspace)
            ->whereHas('integrationAccount', fn ($q) => $q->where('user_id', $user->id))
            ->whereBetween('starts_at', [$rangeStart, $rangeEnd])
            // A row carrying meeting_id belongs to a Cadence meeting, which is
            // rendered from the meeting itself.
            ->whereNull('meeting_id')
            ->when($renderedExternalIds, fn ($q) => $q->whereNotIn('external_id', $renderedExternalIds))
            ->orderBy('starts_at')
            ->get([
                'id', 'title', 'starts_at', 'ends_at', 'location', 'all_day',
                'recurring_event_id', 'recurrence', 'html_link', 'conference_url',
                'integration_account_id',
            ])
            ->map(fn (CalendarEvent $e) => [
                'id' => 'external-'.$e->id,
                'source' => 'external',
                'title' => $e->title,
                'starts_at' => $e->starts_at?->toIso8601String(),
                'ends_at' => $e->ends_at?->toIso8601String(),
                'url' => $e->html_link,
                'meta' => $e->location ?: $e->conference_url,
                'meeting_type' => null,
                'all_day' => (bool) $e->all_day,
                'recurring' => $e->recurring_event_id !== null || ! empty($e->recurrence),
                'task_id' => null,
                'external_event_id' => null,
            ]);
    }

    /**
     * Tasks with a date, as all-day chips.
     *
     * Two suppressions keep a task from repeating: a task that was scheduled as a
     * meeting is drawn as that meeting instead, and the occurrences spawned from
     * a recurring task are hidden when the parent is scheduled, because the
     * provider expands one RRULE event for the whole series.
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected function taskEvents(
        Workspace $workspace,
        $user,
        CarbonImmutable $rangeStart,
        CarbonImmutable $rangeEnd,
    ): Collection {
        $scheduledParentIds = Task::query()
            ->forWorkspace($workspace)
            ->whereNotNull('meeting_id')
            ->pluck('id')
            ->all();

        return Task::query()
            ->forWorkspace($workspace)
            ->whereNull('meeting_id')
            ->whereNotNull('due_date')
            ->whereBetween('due_date', [$rangeStart->toDateString(), $rangeEnd->toDateString()])
            ->when($scheduledParentIds, fn ($q) => $q->where(
                fn ($q) => $q->whereNull('recurrence_parent_id')
                    ->orWhereNotIn('recurrence_parent_id', $scheduledParentIds),
            ))
            ->where(function ($q) use ($user, $workspace) {
                if ($workspace->roleFor($user)?->canManageWorkspace()) {
                    return;
                }
                $q->where('created_by', $user->id)
                    ->orWhereHas('assignees', fn ($q) => $q->where('users.id', $user->id))
                    ->orWhereHas('project.members', fn ($q) => $q->where('users.id', $user->id));
            })
            ->orderBy('due_date')
            ->get(['id', 'title', 'due_date', 'category', 'completed_at'])
            ->map(fn (Task $t) => [
                'id' => 'task-'.$t->id,
                'source' => 'task',
                'title' => $t->title,
                'starts_at' => $t->due_date->startOfDay()->toIso8601String(),
                'ends_at' => $t->due_date->endOfDay()->toIso8601String(),
                'url' => route('tasks.show', $t->id),
                'meta' => $t->category,
                'meeting_type' => null,
                'all_day' => true,
                'recurring' => false,
                'task_id' => $t->id,
                'external_event_id' => null,
                'completed' => $t->completed_at !== null,
            ]);
    }

    /**
     * Travel days — all dates between departs_at and returns_at for any trip
     * belonging to the user that intersects the visible range.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function travelDays(
        Workspace $workspace,
        $user,
        CarbonImmutable $rangeStart,
        CarbonImmutable $rangeEnd,
    ): array {
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

        return $tripDays;
    }

    /**
     * Personal events overlay — birthdays, anniversaries (yearly recurring).
     *
     * @return array<int, array<string, mixed>>
     */
    protected function personalDays($user, CarbonImmutable $rangeStart, CarbonImmutable $rangeEnd): array
    {
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

        return $personalDays;
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
