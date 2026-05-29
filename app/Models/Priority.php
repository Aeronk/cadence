<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Database\Factories\PriorityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Priority extends Model
{
    /** @use HasFactory<PriorityFactory> */
    use BelongsToWorkspace, HasFactory;

    protected $fillable = [
        'workspace_id',
        'name',
        'slug',
        'color',
        'level',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'level' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Priority $priority): void {
            if (empty($priority->slug)) {
                $priority->slug = static::uniqueSlugFor((int) $priority->workspace_id, $priority->name);
            }
        });
    }

    public static function uniqueSlugFor(int $workspaceId, string $name): string
    {
        $base = Str::slug($name) ?: 'priority';
        $slug = $base;
        $i = 2;

        while (static::where('workspace_id', $workspaceId)->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    public static function seedDefaultsFor(Workspace $workspace): void
    {
        $defaults = [
            ['name' => 'Low', 'color' => 'gray', 'level' => 1, 'is_default' => false],
            ['name' => 'Medium', 'color' => 'blue', 'level' => 2, 'is_default' => true],
            ['name' => 'High', 'color' => 'orange', 'level' => 3, 'is_default' => false],
            ['name' => 'Urgent', 'color' => 'red', 'level' => 4, 'is_default' => false],
        ];

        foreach ($defaults as $row) {
            $workspace->priorities()->firstOrCreate(
                ['slug' => Str::slug($row['name'])],
                $row + ['name' => $row['name']]
            );
        }
    }
}
