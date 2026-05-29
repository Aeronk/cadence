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
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'due_date' => 'date',
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
