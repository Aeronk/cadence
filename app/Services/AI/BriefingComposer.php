<?php

namespace App\Services\AI;

use App\Models\DailyBriefing;
use App\Models\Meeting;
use App\Models\Task;
use App\Models\Trip;
use App\Models\User;
use App\Models\Workspace;
use Carbon\CarbonImmutable;

class BriefingComposer
{
    public function __construct(protected Provider $ai) {}

    public function composeFor(User $user, Workspace $workspace, ?CarbonImmutable $date = null): DailyBriefing
    {
        $date ??= CarbonImmutable::now()->startOfDay();
        $dayStart = $date->startOfDay();
        $dayEnd = $date->endOfDay();

        $payload = [
            'tasks_due_today' => Task::query()
                ->forWorkspace($workspace)
                ->whereHas('assignees', fn ($q) => $q->where('users.id', $user->id))
                ->whereNull('completed_at')
                ->whereDate('due_date', $date->toDateString())
                ->get(['id', 'title', 'priority_id'])
                ->map(fn ($t) => ['id' => $t->id, 'title' => $t->title])
                ->all(),
            'overdue_tasks' => Task::query()
                ->forWorkspace($workspace)
                ->whereHas('assignees', fn ($q) => $q->where('users.id', $user->id))
                ->whereNull('completed_at')
                ->whereDate('due_date', '<', $date->toDateString())
                ->get(['id', 'title'])
                ->map(fn ($t) => ['id' => $t->id, 'title' => $t->title])
                ->all(),
            'meetings_today' => Meeting::query()
                ->forWorkspace($workspace)
                ->whereBetween('starts_at', [$dayStart, $dayEnd])
                ->where(function ($q) use ($user) {
                    $q->where('host_id', $user->id)
                        ->orWhereHas('attendees', fn ($q) => $q->where('users.id', $user->id));
                })
                ->orderBy('starts_at')
                ->get(['id', 'title', 'starts_at', 'ends_at', 'location'])
                ->map(fn ($m) => [
                    'id' => $m->id,
                    'title' => $m->title,
                    'starts_at' => $m->starts_at->format('H:i'),
                    'location' => $m->location,
                ])
                ->all(),
            'travel_today' => Trip::query()
                ->forWorkspace($workspace)
                ->where('user_id', $user->id)
                ->intersectingRange($dayStart, $dayEnd)
                ->get(['destination', 'departs_at', 'returns_at'])
                ->map(fn ($t) => ['destination' => $t->destination])
                ->all(),
        ];

        $prompt = $this->buildPrompt($user->name, $payload);
        $summary = trim($this->ai->complete([
            ['role' => 'system', 'content' => 'You are a concise executive chief of staff. Produce a 5-line morning briefing. No markdown headings.'],
            ['role' => 'user', 'content' => $prompt],
        ]));

        return DailyBriefing::updateOrCreate(
            [
                'user_id' => $user->id,
                'workspace_id' => $workspace->id,
                'briefing_date' => $date->toDateString(),
            ],
            [
                'summary' => $summary !== '' ? $summary : 'No activity scheduled.',
                'payload' => $payload,
            ],
        );
    }

    protected function buildPrompt(string $name, array $payload): string
    {
        $lines = [
            "Good morning, {$name}. Today's snapshot:",
            "- Tasks due today: " . count($payload['tasks_due_today']),
            "- Overdue tasks: " . count($payload['overdue_tasks']),
            "- Meetings: " . count($payload['meetings_today']),
            "- Travel: " . count($payload['travel_today']),
        ];

        foreach (['tasks_due_today', 'overdue_tasks', 'meetings_today'] as $bucket) {
            foreach ($payload[$bucket] as $item) {
                $lines[] = "  · {$bucket}: " . ($item['title'] ?? $item['destination'] ?? '');
            }
        }

        $lines[] = "Write a brief plan focused on the most critical 2-3 priorities.";
        return implode("\n", $lines);
    }
}
