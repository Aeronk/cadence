<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Database\Factories\GoalFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Goal extends Model
{
    /** @use HasFactory<GoalFactory> */
    use BelongsToWorkspace, HasFactory, SoftDeletes;

    public const TYPE_VISION = 'vision';

    public const TYPE_GOAL = 'goal';

    public const TYPE_OBJECTIVE = 'objective';

    public const STATUS_ON_TRACK = 'on_track';

    public const STATUS_AT_RISK = 'at_risk';

    public const STATUS_OFF_TRACK = 'off_track';

    public const STATUSES = [self::STATUS_ON_TRACK, self::STATUS_AT_RISK, self::STATUS_OFF_TRACK];

    public const TYPES = [self::TYPE_VISION, self::TYPE_GOAL, self::TYPE_OBJECTIVE];

    public const HORIZONS = ['year', 'quarter', 'month'];

    protected $fillable = [
        'workspace_id',
        'user_id',
        'parent_id',
        'type',
        'title',
        'description',
        'horizon',
        'status',
        'target_date',
        'progress',
        'position',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'target_date' => 'date',
            'completed_at' => 'datetime',
            'progress' => 'integer',
            'position' => 'integer',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('position')->orderBy('title');
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(Milestone::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Computes progress as the average of children + milestones.
     * Leaf nodes use the stored progress field directly.
     *
     * A goal someone has ticked off reads 100 regardless: leaving a completed
     * goal showing 40% because two of its milestones were never closed makes the
     * tick and the bar contradict each other.
     */
    public function computedProgress(): int
    {
        if ($this->completed_at !== null) {
            return 100;
        }

        $childRows = $this->children->map(fn ($c) => $c->computedProgress());
        $milestoneRows = $this->milestones->pluck('progress');

        $all = $childRows->concat($milestoneRows);
        if ($all->isEmpty()) {
            return (int) $this->progress;
        }

        return (int) round($all->average());
    }

    /**
     * Ids of every goal beneath this one, plus its own.
     *
     * Used to stop a goal being reparented onto its own descendant, which would
     * detach the whole branch from every root and leave it unreachable in the
     * tree the index builds.
     *
     * @return array<int, int>
     */
    public function descendantIds(): array
    {
        $ids = [$this->id];
        $frontier = [$this->id];

        // Iterative rather than recursive so a malformed chain that already
        // contains a cycle cannot recurse forever.
        while ($frontier !== []) {
            $next = self::query()
                ->whereIn('parent_id', $frontier)
                ->whereNotIn('id', $ids)
                ->pluck('id')
                ->all();

            if ($next === []) {
                break;
            }

            $ids = array_merge($ids, $next);
            $frontier = $next;
        }

        return $ids;
    }

    /** Whether this goal is past its target date without being finished. */
    public function isOverdue(): bool
    {
        return $this->completed_at === null
            && $this->target_date !== null
            && $this->target_date->isPast();
    }
}
