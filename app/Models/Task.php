<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use App\Models\Concerns\HasComments;
use App\Models\Concerns\HasTags;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use BelongsToWorkspace, HasComments, HasFactory, HasTags, SoftDeletes;

    protected $fillable = [
        'workspace_id',
        'project_id',
        'parent_id',
        'milestone_id',
        'status_id',
        'priority_id',
        'category',
        'recurrence_rule',
        'recurrence_ends_on',
        'recurrence_parent_id',
        'created_by',
        'title',
        'description',
        'start_date',
        'due_date',
        'position',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'due_date' => 'date',
            'completed_at' => 'datetime',
            'position' => 'integer',
            'recurrence_ends_on' => 'date',
        ];
    }

    public function isRecurring(): bool
    {
        return ! empty($this->recurrence_rule) && $this->recurrence_rule !== 'none';
    }

    public function nextOccurrenceDate(): ?\Carbon\CarbonImmutable
    {
        if (! $this->isRecurring() || ! $this->due_date) {
            return null;
        }

        $next = match ($this->recurrence_rule) {
            'daily' => $this->due_date->addDay(),
            'weekly' => $this->due_date->addWeek(),
            'monthly' => $this->due_date->addMonth(),
            'yearly' => $this->due_date->addYear(),
            default => null,
        };

        if (! $next) return null;
        if ($this->recurrence_ends_on && $next->greaterThan($this->recurrence_ends_on)) {
            return null;
        }

        return \Carbon\CarbonImmutable::parse($next);
    }

    protected static function booted(): void
    {
        static::creating(function (Task $task): void {
            if ($task->position === null || $task->position === 0) {
                $task->position = (static::query()
                    ->where('project_id', $task->project_id)
                    ->where('parent_id', $task->parent_id)
                    ->max('position') ?? -1) + 1;
            }

            if (! $task->workspace_id && $task->project_id) {
                $task->workspace_id = Project::query()->whereKey($task->project_id)->value('workspace_id');
            }
        });
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function milestone(): BelongsTo
    {
        return $this->belongsTo(Milestone::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function subtasks(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

    public function priority(): BelongsTo
    {
        return $this->belongsTo(Priority::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_user')->withTimestamps();
    }

    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }

    public function markCompleted(): void
    {
        $this->forceFill(['completed_at' => now()])->save();
    }

    public function markIncomplete(): void
    {
        $this->forceFill(['completed_at' => null])->save();
    }
}
