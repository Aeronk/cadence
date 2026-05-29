<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    public function definition(): array
    {
        $workspaceId = Workspace::factory();

        return [
            'workspace_id' => $workspaceId,
            'created_by' => User::factory(),
            'status_id' => null,
            'priority_id' => null,
            'title' => fake()->catchPhrase(),
            'description' => fake()->optional()->paragraph(),
            'start_date' => fake()->optional()->dateTimeBetween('-1 month', '+1 week'),
            'due_date' => fake()->optional()->dateTimeBetween('+1 week', '+3 months'),
        ];
    }

    public function archived(): static
    {
        return $this->state(fn () => ['archived_at' => now()]);
    }
}
