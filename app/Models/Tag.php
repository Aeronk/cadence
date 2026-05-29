<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Database\Factories\TagFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Str;

class Tag extends Model
{
    /** @use HasFactory<TagFactory> */
    use BelongsToWorkspace, HasFactory;

    protected $fillable = [
        'workspace_id',
        'name',
        'slug',
        'color',
    ];

    protected static function booted(): void
    {
        static::creating(function (Tag $tag): void {
            if (empty($tag->slug)) {
                $tag->slug = static::uniqueSlugFor((int) $tag->workspace_id, $tag->name);
            }
        });
    }

    public static function uniqueSlugFor(int $workspaceId, string $name): string
    {
        $base = Str::slug($name) ?: 'tag';
        $slug = $base;
        $i = 2;

        while (static::where('workspace_id', $workspaceId)->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    public function projects(): MorphToMany
    {
        return $this->morphedByMany(Project::class, 'taggable')->withTimestamps();
    }

    public function tasks(): MorphToMany
    {
        return $this->morphedByMany(Task::class, 'taggable')->withTimestamps();
    }
}
