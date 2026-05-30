<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectFile>
 *
 * @method ProjectFile create($attributes = [], ?\Illuminate\Database\Eloquent\Model $parent = null)
 */
class ProjectFileFactory extends Factory
{
    public function definition(): array
    {
        $project = Project::factory();

        return [
            'project_id' => $project,
            'workspace_id' => function (array $attrs) {
                return Project::query()->whereKey($attrs['project_id'])->value('workspace_id');
            },
            'uploaded_by' => User::factory(),
            'original_name' => fake()->word().'.pdf',
            'disk' => 'private',
            'path' => 'projects/'.fake()->uuid().'.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => fake()->numberBetween(1024, 1024 * 1024),
        ];
    }
}
