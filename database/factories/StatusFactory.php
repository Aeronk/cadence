<?php

namespace Database\Factories;

use App\Models\Status;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Status>
 */
class StatusFactory extends Factory
{
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'name' => fake()->randomElement(['Backlog', 'Todo', 'In Progress', 'Review', 'Done']),
            'color' => fake()->randomElement(['gray', 'blue', 'green', 'orange', 'red']),
            'position' => fake()->numberBetween(0, 10),
            'is_default' => false,
            'is_completed' => false,
        ];
    }
}
