<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    public function definition(): array
    {
        $project = Project::factory();

        return [
            'project_id' => $project,
            'workspace_id' => function (array $attrs) {
                return Project::query()->whereKey($attrs['project_id'])->value('workspace_id');
            },
            'created_by' => User::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->optional()->paragraph(),
            'position' => 0,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn () => ['completed_at' => now()]);
    }

    public function subtaskOf(Task $parent): static
    {
        return $this->state(fn () => [
            'parent_id' => $parent->id,
            'project_id' => $parent->project_id,
            'workspace_id' => $parent->workspace_id,
        ]);
    }
}
