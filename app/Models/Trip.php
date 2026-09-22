<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Database\Factories\TripFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Trip extends Model
{
    /** @use HasFactory<TripFactory> */
    use BelongsToWorkspace, HasFactory, SoftDeletes;

    public const STATUS_PLANNED = 'planned';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'workspace_id',
        'user_id',
        'project_id',
        'name',
        'purpose',
        'destination_country',
        'destination_city',
        'departs_at',
        'returns_at',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'departs_at' => 'datetime',
            'returns_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** The project this travel is being undertaken for, if any. */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** Work that has to happen on this trip. */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function segments(): HasMany
    {
        return $this->hasMany(TripSegment::class)->orderBy('starts_at');
    }

    public function checklist(): HasMany
    {
        return $this->hasMany(TripChecklistItem::class)->orderBy('position');
    }

    public function scopeIntersectingRange(Builder $q, $start, $end): Builder
    {
        return $q->where('departs_at', '<=', $end)
            ->where('returns_at', '>=', $start);
    }
}
