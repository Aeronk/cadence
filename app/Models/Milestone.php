<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Database\Factories\MilestoneFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Milestone extends Model
{
    /** @use HasFactory<MilestoneFactory> */
    use BelongsToWorkspace, HasFactory, SoftDeletes;

    protected $fillable = [
        'workspace_id',
        'project_id',
        'created_by',
        'title',
        'description',
        'due_date',
        'progress',
        'completed_at',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'completed_at' => 'datetime',
            'progress' => 'integer',
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }
}
