<?php

namespace Database\Factories;

use App\Models\Goal;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Goal>
 *
 * @method Goal create($attributes = [], ?\Illuminate\Database\Eloquent\Model $parent = null)
 */
class GoalFactory extends Factory
{
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'user_id' => User::factory(),
            'type' => 'goal',
            'title' => fake()->catchPhrase(),
            'description' => fake()->optional()->paragraph(),
            'horizon' => fake()->randomElement(['year', 'quarter', 'month']),
            'target_date' => fake()->dateTimeBetween('+1 month', '+1 year'),
            'progress' => 0,
        ];
    }
}
