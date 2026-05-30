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

    protected $fillable = [
        'workspace_id',
        'user_id',
        'parent_id',
        'type',
        'title',
        'description',
        'horizon',
        'target_date',
        'progress',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'target_date' => 'date',
            'completed_at' => 'datetime',
            'progress' => 'integer',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
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
     * Computes progress as the weighted average of children + milestones.
     * Leaf nodes use the stored progress field directly.
     */
    public function computedProgress(): int
    {
        $childRows = $this->children->map(fn ($c) => $c->computedProgress());
        $milestoneRows = $this->milestones->pluck('progress');

        $all = $childRows->concat($milestoneRows);
        if ($all->isEmpty()) {
            return (int) $this->progress;
        }

        return (int) round($all->average());
    }
}
