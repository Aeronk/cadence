<?php

namespace Database\Factories;

use App\Models\ActivityLog;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActivityLog>
 */
class ActivityLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'actor_id' => User::factory(),
            'action' => fake()->randomElement(['created', 'updated', 'deleted', 'assigned']),
            'description' => fake()->sentence(),
            'properties' => null,
        ];
    }
}
