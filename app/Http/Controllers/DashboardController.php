<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\Task;
use App\Models\Todo;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $workspace = $user->currentWorkspace();

        if (! $workspace) {
            return Inertia::render('Dashboard', ['stats' => null]);
        }

        $isManager = $workspace->roleFor($user)?->canManageWorkspace() ?? false;

        $myTasks = Task::query()
            ->forWorkspace($workspace)
            ->whereNull('completed_at')
            ->where(function ($q) use ($user) {
                $q->whereHas('assignees', fn ($q) => $q->where('users.id', $user->id))
                    ->orWhere('created_by', $user->id);
            })
            ->orderBy('due_date')
            ->limit(8)
            ->get(['id', 'title', 'due_date', 'priority_id', 'status_id', 'project_id']);

        return Inertia::render('Dashboard', [
            'stats' => [
                'projects_count' => Project::query()->forWorkspace($workspace)
                    ->when(! $isManager, fn ($q) => $q->where(function ($q) use ($user) {
                        $q->where('created_by', $user->id)
                            ->orWhereHas('members', fn ($q) => $q->where('users.id', $user->id));
                    }))
                    ->count(),
                'my_open_tasks_count' => Task::query()->forWorkspace($workspace)
                    ->whereNull('completed_at')
                    ->where(function ($q) use ($user) {
                        $q->whereHas('assignees', fn ($q) => $q->where('users.id', $user->id))
                            ->orWhere('created_by', $user->id);
                    })
                    ->count(),
                'open_todos_count' => Todo::query()
                    ->where('user_id', $user->id)
                    ->forWorkspace($workspace)
                    ->whereNull('completed_at')
                    ->count(),
                'upcoming_meetings_count' => Meeting::query()
                    ->forWorkspace($workspace)
                    ->where('starts_at', '>=', now())
                    ->where(function ($q) use ($user) {
                        $q->where('host_id', $user->id)
                            ->orWhereHas('attendees', fn ($q) => $q->where('users.id', $user->id));
                    })
                    ->count(),
            ],
            'my_tasks' => $myTasks,
            'upcoming_meetings' => Meeting::query()
                ->forWorkspace($workspace)
                ->where('starts_at', '>=', now())
                ->where(function ($q) use ($user) {
                    $q->where('host_id', $user->id)
                        ->orWhereHas('attendees', fn ($q) => $q->where('users.id', $user->id));
                })
                ->orderBy('starts_at')
                ->limit(5)
                ->get(['id', 'title', 'starts_at', 'ends_at']),
            'recent_activity' => ActivityLog::query()
                ->forWorkspace($workspace)
                ->with('actor:id,name')
                ->latest()
                ->limit(10)
                ->get(['id', 'actor_id', 'action', 'description', 'created_at']),
            'charts' => $this->charts($user, $workspace),
        ]);
    }

    /**
     * Lightweight chart series for the dashboard — small payloads, all aggregated.
     */
    protected function charts($user, $workspace): array
    {
        $weekStart = now()->startOfWeek();

        $tasksThisWeek = Task::query()
            ->forWorkspace($workspace)
            ->where(function ($q) use ($user) {
                $q->whereHas('assignees', fn ($q) => $q->where('users.id', $user->id))
                    ->orWhere('created_by', $user->id);
            })
            ->where('created_at', '>=', $weekStart)
            ->selectRaw('COUNT(*) as total, SUM(CASE WHEN completed_at IS NOT NULL THEN 1 ELSE 0 END) as done')
            ->first();

        $byPriority = Task::query()
            ->forWorkspace($workspace)
            ->whereNull('completed_at')
            ->join('priorities', 'priorities.id', '=', 'tasks.priority_id')
            ->selectRaw('priorities.level as level, priorities.name as name, COUNT(*) as count')
            ->groupBy('priorities.level', 'priorities.name')
            ->orderBy('priorities.level')
            ->get()
            ->map(fn ($r) => ['label' => $r->name, 'count' => (int) $r->count, 'level' => (int) $r->level]);

        // Last 14 days of activity, bucketed by day.
        $activitySeries = ActivityLog::query()
            ->forWorkspace($workspace)
            ->where('created_at', '>=', now()->subDays(13)->startOfDay())
            ->selectRaw("DATE(created_at) as day, COUNT(*) as count")
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('count', 'day');

        $days = [];
        for ($i = 13; $i >= 0; $i--) {
            $d = now()->subDays($i)->toDateString();
            $days[] = [
                'day' => $d,
                'label' => now()->subDays($i)->format('D'),
                'count' => (int) ($activitySeries[$d] ?? 0),
            ];
        }

        return [
            'this_week' => [
                'total' => (int) ($tasksThisWeek->total ?? 0),
                'done' => (int) ($tasksThisWeek->done ?? 0),
            ],
            'by_priority' => $byPriority,
            'activity_14d' => $days,
        ];
    }
}
