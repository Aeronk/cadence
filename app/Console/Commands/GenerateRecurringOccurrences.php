<?php

namespace App\Console\Commands;

use App\Models\Task;
use App\Models\Todo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenerateRecurringOccurrences extends Command
{
    protected $signature = 'recurring:generate';

    protected $description = 'Spawn the next occurrence for every completed recurring task/todo.';

    public function handle(): int
    {
        $tasks = $this->spawnTasks();
        $todos = $this->spawnTodos();

        $this->info("Spawned {$tasks} task occurrence(s) and {$todos} todo occurrence(s).");

        return self::SUCCESS;
    }

    protected function spawnTasks(): int
    {
        $created = 0;

        Task::query()
            ->whereNotNull('recurrence_rule')
            ->whereNotNull('completed_at')
            ->whereNotNull('due_date')
            ->orderBy('id')
            ->chunkById(200, function ($chunk) use (&$created) {
                foreach ($chunk as $task) {
                    DB::transaction(function () use ($task, &$created) {
                        /** @var Task $locked */
                        $locked = Task::query()->lockForUpdate()->find($task->id);
                        if (! $locked || ! $locked->isRecurring()) {
                            return;
                        }
                        $nextDate = $locked->nextOccurrenceDate();
                        if (! $nextDate) {
                            return;
                        }

                        // Skip if we've already spawned the next sibling for this row.
                        $alreadySpawned = Task::query()
                            ->where('recurrence_parent_id', $locked->id)
                            ->whereDate('due_date', $nextDate->toDateString())
                            ->exists();
                        if ($alreadySpawned) {
                            return;
                        }

                        $next = $locked->replicate([
                            'completed_at',
                            'position',
                        ]);
                        $next->due_date = $nextDate;
                        if ($locked->start_date && $locked->due_date) {
                            $delta = $locked->start_date->diffInDays($locked->due_date);
                            $next->start_date = $nextDate->subDays($delta);
                        }
                        $next->completed_at = null;
                        $next->recurrence_parent_id = $locked->id;
                        $next->save();

                        $created++;
                    });
                }
            });

        return $created;
    }

    protected function spawnTodos(): int
    {
        $created = 0;

        Todo::query()
            ->whereNotNull('recurrence_rule')
            ->whereNotNull('completed_at')
            ->whereNotNull('due_date')
            ->orderBy('id')
            ->chunkById(200, function ($chunk) use (&$created) {
                foreach ($chunk as $todo) {
                    DB::transaction(function () use ($todo, &$created) {
                        /** @var Todo $locked */
                        $locked = Todo::query()->lockForUpdate()->find($todo->id);
                        if (! $locked || ! $locked->isRecurring()) {
                            return;
                        }
                        $nextDate = $locked->nextOccurrenceDate();
                        if (! $nextDate) {
                            return;
                        }

                        $alreadySpawned = Todo::query()
                            ->where('recurrence_parent_id', $locked->id)
                            ->whereDate('due_date', $nextDate->toDateString())
                            ->exists();
                        if ($alreadySpawned) {
                            return;
                        }

                        $next = $locked->replicate(['completed_at', 'position']);
                        $next->due_date = $nextDate;
                        $next->completed_at = null;
                        $next->recurrence_parent_id = $locked->id;
                        $next->save();

                        $created++;
                    });
                }
            });

        return $created;
    }
}
