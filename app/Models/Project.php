<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use App\Models\Concerns\HasComments;
use App\Models\Concerns\HasTags;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use BelongsToWorkspace, HasComments, HasFactory, HasTags, SoftDeletes;

    protected $fillable = [
        'workspace_id',
        'status_id',
        'priority_id',
        'created_by',
        'title',
        'key',
        'description',
        'start_date',
        'due_date',
        'budget',
        'budget_currency',
        'state',
        'completed_at',
        'on_hold_at',
        'archived_at',
    ];

    public const STATE_ACTIVE = 'active';

    public const STATE_ON_HOLD = 'on_hold';

    public const STATE_COMPLETED = 'completed';

    public const STATES = [self::STATE_ACTIVE, self::STATE_ON_HOLD, self::STATE_COMPLETED];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'due_date' => 'date',
            'budget' => 'decimal:2',
            'completed_at' => 'datetime',
            'on_hold_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
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

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function clients(): BelongsToMany
    {
        return $this->belongsToMany(Client::class, 'client_project')->withTimestamps();
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(Milestone::class)->orderBy('position');
    }

    public function files(): HasMany
    {
        return $this->hasMany(ProjectFile::class)->latest();
    }

    /**
     * The goals this project serves. Many-to-many because one project often
     * advances more than one goal at a time.
     */
    public function goals(): BelongsToMany
    {
        return $this->belongsToMany(Goal::class, 'goal_project')->withTimestamps();
    }

    public function meetings(): HasMany
    {
        return $this->hasMany(Meeting::class);
    }

    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(Note::class);
    }

    public function todos(): HasMany
    {
        return $this->hasMany(Todo::class);
    }

    /**
     * How far along the project is, as a percentage.
     *
     * Milestones win when there are any, because they are the deliberate
     * checkpoints someone set; otherwise it falls back to the share of tasks
     * that are done. A project nobody has broken down yet reads 0 rather than
     * dividing by zero, and one marked complete reads 100 so the badge and the
     * bar cannot disagree.
     */
    public function computedProgress(): int
    {
        if ($this->completed_at !== null || $this->state === self::STATE_COMPLETED) {
            return 100;
        }

        $milestones = $this->relationLoaded('milestones')
            ? $this->milestones
            : $this->milestones()->get(['id', 'progress']);

        if ($milestones->isNotEmpty()) {
            return (int) round($milestones->avg('progress'));
        }

        // Uses eager-loaded counts when the caller supplied them. The goals
        // page walks a whole tree of projects, and querying twice per project
        // there turned one page into dozens of round trips.
        $total = $this->tasks_count ?? $this->tasks()->count();

        if ($total === 0) {
            return 0;
        }

        $done = $this->completed_tasks_count
            ?? $this->tasks()->whereNotNull('completed_at')->count();

        return (int) round($done / $total * 100);
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function hasMember(User $user): bool
    {
        return $this->members()->where('users.id', $user->id)->exists()
            || $this->created_by === $user->id;
    }
}
