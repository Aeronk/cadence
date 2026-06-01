<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Meeting;
use App\Models\PersonalEvent;
use App\Models\Project;
use App\Models\Task;
use App\Models\Todo;
use App\Models\Trip;
use App\Models\User;
use App\Models\Workspace;
use Carbon\CarbonImmutable;
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
            'briefing' => $this->personalBriefing($user, $workspace),
            'status_overview' => $this->statusOverview($user, $workspace),
        ]);
    }

    /**
     * "Project Status Overview" panel — counts of projects by state plus
     * tasks grouped by their workspace status column.
     */
    protected function statusOverview(User $user, Workspace $workspace): array
    {
        $today = now()->toDateString();
        $projectsQuery = Project::query()->forWorkspace($workspace);

        $active = (clone $projectsQuery)
            ->whereNull('archived_at')
            ->where('state', Project::STATE_ACTIVE)
            ->count();
        $completed = (clone $projectsQuery)
            ->where('state', Project::STATE_COMPLETED)
            ->count();
        $overdue = (clone $projectsQuery)
            ->whereNull('archived_at')
            ->where('state', '!=', Project::STATE_COMPLETED)
            ->whereNotNull('due_date')
            ->where('due_date', '<', $today)
            ->count();
        $totalProjects = (clone $projectsQuery)->count();
        $overallProgress = $totalProjects > 0
            ? (int) round(($completed / $totalProjects) * 100)
            : 0;

        $taskStages = $workspace->statuses()
            ->orderBy('position')
            ->get(['id', 'name', 'color'])
            ->map(function ($status) use ($workspace, $user) {
                $count = Task::query()
                    ->forWorkspace($workspace)
                    ->where('status_id', $status->id)
                    ->where(function ($q) use ($user) {
                        $q->whereHas('assignees', fn ($q) => $q->where('users.id', $user->id))
                            ->orWhere('created_by', $user->id);
                    })
                    ->count();
                return [
                    'name' => $status->name,
                    'color' => $status->color,
                    'count' => $count,
                ];
            })
            ->all();

        return [
            'projects' => [
                'active' => $active,
                'completed' => $completed,
                'overdue' => $overdue,
                'total' => $totalProjects,
                'overall_progress' => $overallProgress,
            ],
            'task_stages' => $taskStages,
        ];
    }

    /**
     * A short "hey {name}, here's what's coming up" list for the dashboard hero.
     * Pulls trips, birthdays/anniversaries, meetings, and projects nearing their
     * due date so the user lands in context.
     */
    protected function personalBriefing(User $user, Workspace $workspace): array
    {
        $now = CarbonImmutable::now();
        $weekOut = $now->addDays(7);

        $trips = Trip::query()
            ->forWorkspace($workspace)
            ->where('user_id', $user->id)
            ->where('departs_at', '>=', $now)
            ->where('departs_at', '<=', $weekOut)
            ->orderBy('departs_at')
            ->limit(3)
            ->get(['id', 'name', 'destination_city', 'destination_country', 'departs_at'])
            ->map(fn ($t) => [
                'id' => $t->id,
                'destination' => trim(($t->destination_city ?? '').' '.($t->destination_country ?? '')) ?: $t->name,
                'name' => $t->name,
                'departs_at' => $t->departs_at->toDateString(),
                'days_away' => max(0, (int) $now->diffInDays($t->departs_at, false)),
            ])
            ->all();

        $events = PersonalEvent::query()
            ->where('user_id', $user->id)
            ->get(['id', 'title', 'category', 'event_date', 'recurs_yearly'])
            ->flatMap(function ($ev) use ($now, $weekOut) {
                $hits = [];
                $start = (int) $now->format('Y');
                $end = (int) $weekOut->format('Y');
                $base = CarbonImmutable::parse($ev->event_date);
                for ($y = $start; $y <= $end; $y++) {
                    $candidate = $ev->recurs_yearly ? $base->setYear($y) : $base;
                    if ($candidate->gte($now->startOfDay()) && $candidate->lte($weekOut)) {
                        $hits[] = [
                            'id' => $ev->id,
                            'title' => $ev->title,
                            'category' => $ev->category,
                            'date' => $candidate->toDateString(),
                            'days_away' => max(0, (int) $now->diffInDays($candidate, false)),
                        ];
                    }
                    if (! $ev->recurs_yearly) break;
                }
                return $hits;
            })
            ->sortBy('date')
            ->values()
            ->take(4)
            ->all();

        $meetings = Meeting::query()
            ->forWorkspace($workspace)
            ->where('starts_at', '>=', $now)
            ->where('starts_at', '<=', $weekOut)
            ->where(function ($q) use ($user) {
                $q->where('host_id', $user->id)
                    ->orWhereHas('attendees', fn ($q) => $q->where('users.id', $user->id));
            })
            ->with('host:id,name')
            ->orderBy('starts_at')
            ->limit(3)
            ->get(['id', 'title', 'host_id', 'starts_at'])
            ->map(fn ($m) => [
                'id' => $m->id,
                'title' => $m->title,
                'host' => $m->host?->name,
                'starts_at' => $m->starts_at->toIso8601String(),
                'when' => $m->starts_at->isToday()
                    ? 'today · '.$m->starts_at->format('H:i')
                    : $m->starts_at->format('D j M · H:i'),
            ])
            ->all();

        $projects = Project::query()
            ->forWorkspace($workspace)
            ->whereNotNull('due_date')
            ->where('due_date', '>=', $now->toDateString())
            ->where('due_date', '<=', $weekOut->toDateString())
            ->whereNull('archived_at')
            ->where(function ($q) use ($user) {
                $q->where('created_by', $user->id)
                    ->orWhereHas('members', fn ($q) => $q->where('users.id', $user->id));
            })
            ->orderBy('due_date')
            ->limit(3)
            ->get(['id', 'title', 'due_date'])
            ->map(fn ($p) => [
                'id' => $p->id,
                'title' => $p->title,
                'due_date' => $p->due_date?->toDateString(),
            ])
            ->all();

        return compact('trips', 'events', 'meetings', 'projects');
    }

    /**
     * Lightweight chart series for the dashboard — small payloads, all aggregated.
     */
    protected function charts(User $user, Workspace $workspace): array
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
