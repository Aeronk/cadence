<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Workspace>
 */
class WorkspaceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'owner_id' => User::factory(),
            'name' => fake()->unique()->company(),
            'is_personal' => false,
            'description' => fake()->optional()->sentence(),
        ];
    }

    public function personal(): static
    {
        return $this->state(fn () => ['is_personal' => true]);
    }
}
