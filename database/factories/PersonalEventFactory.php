<?php

namespace Database\Factories;

use App\Models\PersonalEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PersonalEvent>
 *
 * @method PersonalEvent create($attributes = [], ?\Illuminate\Database\Eloquent\Model $parent = null)
 */
class PersonalEventFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'workspace_id' => null,
            'title' => fake()->randomElement(['Mom\'s birthday', 'Anniversary', 'Parent-teacher meeting']),
            'category' => fake()->randomElement(['birthday', 'anniversary', 'school', 'health', 'other']),
            'event_date' => fake()->dateTimeBetween('-1 year', '+1 year'),
            'recurs_yearly' => fake()->boolean(70),
        ];
    }
}
