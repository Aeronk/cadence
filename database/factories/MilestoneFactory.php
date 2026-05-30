<?php

namespace Database\Factories;

use App\Models\Milestone;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Milestone>
 *
 * @method Milestone create($attributes = [], ?\Illuminate\Database\Eloquent\Model $parent = null)
 * @method Milestone createOne($attributes = [])
 */
class MilestoneFactory extends Factory
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
            'title' => fake()->catchPhrase(),
            'description' => fake()->optional()->paragraph(),
            'due_date' => fake()->optional()->dateTimeBetween('+1 week', '+3 months'),
            'progress' => fake()->numberBetween(0, 100),
            'position' => 0,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'progress' => 100,
            'completed_at' => now(),
        ]);
    }
}
