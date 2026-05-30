<?php

namespace App\Http\Controllers\Analytics;

use App\Http\Controllers\Controller;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\Task;
use App\Models\Todo;
use App\Models\Trip;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AnalyticsController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $workspace = $user->currentWorkspace();
        abort_unless($workspace !== null, 404);

        $now = now();
        $rangeStart = CarbonImmutable::parse($now)->subDays(29)->startOfDay();
        $rangeEnd = CarbonImmutable::parse($now)->endOfDay();

        return Inertia::render('Analytics/Index', [
            'range' => [
                'starts_at' => $rangeStart->toDateString(),
                'ends_at' => $rangeEnd->toDateString(),
                'days' => 30,
            ],
            'task_status' => $this->taskStatus($user, $workspace),
            'tasks_by_category' => $this->tasksByCategory($user, $workspace),
            'meeting_hours_by_day' => $this->meetingHoursByDay($user, $workspace, $rangeStart, $rangeEnd),
            'workload' => $this->workload($user, $workspace),
            'life_balance' => $this->lifeBalance($user, $workspace, $rangeStart, $rangeEnd),
            'productivity' => [
                'tasks_completed_30d' => Task::query()
                    ->forWorkspace($workspace)
                    ->whereHas('assignees', fn ($q) => $q->where('users.id', $user->id))
                    ->whereBetween('completed_at', [$rangeStart, $rangeEnd])
                    ->count(),
                'todos_completed_30d' => Todo::query()
                    ->where('user_id', $user->id)
                    ->forWorkspace($workspace)
                    ->whereBetween('completed_at', [$rangeStart, $rangeEnd])
                    ->count(),
                'travel_days_30d' => $this->travelDayCount($user, $workspace, $rangeStart, $rangeEnd),
            ],
        ]);
    }

    protected function taskStatus($user, $workspace): array
    {
        $base = Task::query()
            ->forWorkspace($workspace)
            ->where(function ($q) use ($user) {
                $q->whereHas('assignees', fn ($q) => $q->where('users.id', $user->id))
                    ->orWhere('created_by', $user->id);
            });

        return [
            'open' => (clone $base)->whereNull('completed_at')->count(),
            'completed' => (clone $base)->whereNotNull('completed_at')->count(),
            'overdue' => (clone $base)
                ->whereNull('completed_at')
                ->whereNotNull('due_date')
                ->whereDate('due_date', '<', now())
                ->count(),
        ];
    }

    protected function tasksByCategory($user, $workspace): array
    {
        return Task::query()
            ->forWorkspace($workspace)
            ->whereNotNull('category')
            ->where(function ($q) use ($user) {
                $q->whereHas('assignees', fn ($q) => $q->where('users.id', $user->id))
                    ->orWhere('created_by', $user->id);
            })
            ->selectRaw('category, COUNT(*) as count')
            ->groupBy('category')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($r) => ['category' => $r->category, 'count' => (int) $r->count])
            ->all();
    }

    protected function meetingHoursByDay($user, $workspace, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $meetings = Meeting::query()
            ->forWorkspace($workspace)
            ->whereBetween('starts_at', [$start, $end])
            ->where(function ($q) use ($user) {
                $q->where('host_id', $user->id)
                    ->orWhereHas('attendees', fn ($q) => $q->where('users.id', $user->id));
            })
            ->get(['starts_at', 'ends_at']);

        $buckets = [];
        for ($i = 29; $i >= 0; $i--) {
            $d = $end->subDays($i)->toDateString();
            $buckets[$d] = 0.0;
        }

        foreach ($meetings as $m) {
            $key = $m->starts_at->toDateString();
            if (isset($buckets[$key])) {
                $hours = $m->ends_at->diffInMinutes($m->starts_at) / 60;
                $buckets[$key] += round($hours, 2);
            }
        }

        return collect($buckets)
            ->map(fn ($v, $k) => ['day' => $k, 'hours' => round($v, 2)])
            ->values()
            ->all();
    }

    protected function workload($user, $workspace): array
    {
        // Workspace-wide if user is admin; otherwise just per-user count.
        $isManager = $workspace->roleFor($user)?->canManageWorkspace() ?? false;
        if (! $isManager) {
            return [];
        }

        return $workspace->members()
            ->select('users.id', 'users.name')
            ->get()
            ->map(function ($member) use ($workspace) {
                $open = Task::query()
                    ->forWorkspace($workspace)
                    ->whereHas('assignees', fn ($q) => $q->where('users.id', $member->id))
                    ->whereNull('completed_at')
                    ->count();
                $overdue = Task::query()
                    ->forWorkspace($workspace)
                    ->whereHas('assignees', fn ($q) => $q->where('users.id', $member->id))
                    ->whereNull('completed_at')
                    ->whereDate('due_date', '<', now())
                    ->count();
                return ['user_id' => $member->id, 'name' => $member->name, 'open' => $open, 'overdue' => $overdue];
            })
            ->sortByDesc('open')
            ->values()
            ->all();
    }

    /**
     * Composite life-balance: how much of the user's recent activity falls
     * into each category. Buckets that score zero still show so the radar
     * has full silhouette.
     */
    protected function lifeBalance($user, $workspace, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $taskCounts = Task::query()
            ->forWorkspace($workspace)
            ->whereHas('assignees', fn ($q) => $q->where('users.id', $user->id))
            ->whereBetween('updated_at', [$start, $end])
            ->whereNotNull('category')
            ->selectRaw('category, COUNT(*) as count')
            ->groupBy('category')
            ->pluck('count', 'category');

        $todoCounts = Todo::query()
            ->where('user_id', $user->id)
            ->whereBetween('updated_at', [$start, $end])
            ->whereNotNull('category')
            ->selectRaw('category, COUNT(*) as count')
            ->groupBy('category')
            ->pluck('count', 'category');

        $buckets = ['work', 'personal', 'family', 'church', 'social', 'travel', 'health', 'finance'];
        $scores = [];
        foreach ($buckets as $b) {
            $scores[$b] = (int) ($taskCounts[$b] ?? 0) + (int) ($todoCounts[$b] ?? 0);
        }

        $max = max(1, ...array_values($scores));

        return collect($buckets)
            ->map(fn ($b) => [
                'category' => $b,
                'count' => $scores[$b],
                'pct' => (int) round(($scores[$b] / $max) * 100),
            ])
            ->all();
    }

    protected function travelDayCount($user, $workspace, CarbonImmutable $start, CarbonImmutable $end): int
    {
        $days = 0;
        Trip::query()
            ->forWorkspace($workspace)
            ->where('user_id', $user->id)
            ->intersectingRange($start, $end)
            ->whereNotIn('status', [Trip::STATUS_CANCELLED])
            ->get(['departs_at', 'returns_at'])
            ->each(function ($trip) use (&$days, $start, $end) {
                $cursor = $trip->departs_at->startOfDay();
                $endDay = $trip->returns_at->startOfDay();
                while ($cursor->lessThanOrEqualTo($endDay)) {
                    if ($cursor->greaterThanOrEqualTo($start) && $cursor->lessThanOrEqualTo($end)) {
                        $days++;
                    }
                    $cursor = $cursor->addDay();
                }
            });

        return $days;
    }
}
