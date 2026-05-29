<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Database\Factories\StatusFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Status extends Model
{
    /** @use HasFactory<StatusFactory> */
    use BelongsToWorkspace, HasFactory;

    protected $fillable = [
        'workspace_id',
        'name',
        'slug',
        'color',
        'position',
        'is_default',
        'is_completed',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_completed' => 'boolean',
            'position' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Status $status): void {
            if (empty($status->slug)) {
                $status->slug = static::uniqueSlugFor((int) $status->workspace_id, $status->name);
            }
        });
    }

    public static function uniqueSlugFor(int $workspaceId, string $name): string
    {
        $base = Str::slug($name) ?: 'status';
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
            ['name' => 'Backlog', 'color' => 'slate', 'position' => 0, 'is_default' => true, 'is_completed' => false],
            ['name' => 'Todo', 'color' => 'gray', 'position' => 1, 'is_default' => false, 'is_completed' => false],
            ['name' => 'In Progress', 'color' => 'blue', 'position' => 2, 'is_default' => false, 'is_completed' => false],
            ['name' => 'Done', 'color' => 'green', 'position' => 3, 'is_default' => false, 'is_completed' => true],
        ];

        foreach ($defaults as $row) {
            $workspace->statuses()->firstOrCreate(
                ['slug' => Str::slug($row['name'])],
                $row + ['name' => $row['name']]
            );
        }
    }
}
