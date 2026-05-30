<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Database\Factories\TodoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Todo extends Model
{
    /** @use HasFactory<TodoFactory> */
    use BelongsToWorkspace, HasFactory;

    protected $fillable = [
        'workspace_id',
        'user_id',
        'title',
        'description',
        'priority',
        'category',
        'due_date',
        'completed_at',
        'position',
        'recurrence_rule',
        'recurrence_ends_on',
        'recurrence_parent_id',
    ];

    protected function casts(): array
    {
        return [
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
        if (! $this->isRecurring() || ! $this->due_date) return null;

        $next = match ($this->recurrence_rule) {
            'daily' => $this->due_date->addDay(),
            'weekly' => $this->due_date->addWeek(),
            'monthly' => $this->due_date->addMonth(),
            'yearly' => $this->due_date->addYear(),
            default => null,
        };

        if (! $next) return null;
        if ($this->recurrence_ends_on && $next->greaterThan($this->recurrence_ends_on)) return null;

        return \Carbon\CarbonImmutable::parse($next);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }
}
