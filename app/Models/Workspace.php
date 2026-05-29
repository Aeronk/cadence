<?php

namespace App\Models;

use App\Enums\WorkspaceRole;
use Database\Factories\WorkspaceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Workspace extends Model
{
    /** @use HasFactory<WorkspaceFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'owner_id',
        'name',
        'slug',
        'is_personal',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'is_personal' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Workspace $workspace): void {
            if (empty($workspace->slug)) {
                $workspace->slug = static::generateUniqueSlug($workspace->name);
            }
        });

        static::created(function (Workspace $workspace): void {
            if ($workspace->owner_id && ! $workspace->members()->where('users.id', $workspace->owner_id)->exists()) {
                $workspace->members()->attach($workspace->owner_id, [
                    'role' => WorkspaceRole::Owner->value,
                    'joined_at' => now(),
                ]);
            }

            Status::seedDefaultsFor($workspace);
            Priority::seedDefaultsFor($workspace);
        });
    }

    public function statuses(): HasMany
    {
        return $this->hasMany(Status::class);
    }

    public function priorities(): HasMany
    {
        return $this->hasMany(Priority::class);
    }

    public function tags(): HasMany
    {
        return $this->hasMany(Tag::class);
    }

    public static function generateUniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'workspace';
        $slug = $base;
        $i = 2;

        while (static::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'workspace_user')
            ->withPivot('role', 'joined_at')
            ->withTimestamps();
    }

    public function roleFor(User $user): ?WorkspaceRole
    {
        $row = $this->members()->where('users.id', $user->id)->first();

        return $row ? WorkspaceRole::from($row->pivot->role) : null;
    }

    public function hasMember(User $user): bool
    {
        return $this->roleFor($user) !== null;
    }
}
