<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Database\Factories\MilestoneFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Milestone extends Model
{
    /** @use HasFactory<MilestoneFactory> */
    use BelongsToWorkspace, HasFactory, SoftDeletes;

    protected $fillable = [
        'workspace_id',
        'project_id',
        'goal_id',
        'created_by',
        'title',
        'description',
        'due_date',
        'progress',
        'manual_progress',
        'completed_at',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'completed_at' => 'datetime',
            'progress' => 'integer',
            'manual_progress' => 'integer',
            'position' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Milestone $milestone): void {
            if ($milestone->position === null || $milestone->position === 0) {
                $milestone->position = (static::query()
                    ->where('project_id', $milestone->project_id)
                    ->max('position') ?? -1) + 1;
            }

            if (! $milestone->workspace_id && $milestone->project_id) {
                $milestone->workspace_id = Project::query()->whereKey($milestone->project_id)->value('workspace_id');
            }
        });
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function goal(): BelongsTo
    {
        return $this->belongsTo(Goal::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }

    /**
     * Whether progress is pinned to a hand-entered number rather than derived
     * from the milestone's tasks.
     */
    public function tracksProgressManually(): bool
    {
        return $this->manual_progress !== null;
    }

    /**
     * The effective progress: a manual value wins, otherwise the share of the
     * milestone's tasks that are complete. A milestone with no tasks and no
     * manual value sits at 0 rather than dividing by zero.
     */
    public function resolveProgress(): int
    {
        if ($this->manual_progress !== null) {
            return (int) $this->manual_progress;
        }

        $total = $this->tasks()->count();

        if ($total === 0) {
            return 0;
        }

        $done = $this->tasks()->whereNotNull('completed_at')->count();

        return (int) round($done / $total * 100);
    }

    /**
     * Recompute and cache `progress`. Kept as a stored column so goal rollup and
     * list views can read it without loading every task.
     */
    public function recomputeProgress(): int
    {
        $resolved = $this->resolveProgress();

        if ((int) $this->progress !== $resolved) {
            $this->forceFill(['progress' => $resolved])->save();
        }

        return $resolved;
    }
}
