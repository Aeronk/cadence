<?php

namespace Database\Factories;

use App\Models\Meeting;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Meeting>
 *
 * @method Meeting create($attributes = [], ?\Illuminate\Database\Eloquent\Model $parent = null)
 * @method Meeting createOne($attributes = [])
 */
class MeetingFactory extends Factory
{
    public function definition(): array
    {
        $starts = fake()->dateTimeBetween('+1 day', '+2 weeks');
        $ends = (clone $starts)->modify('+1 hour');

        return [
            'workspace_id' => Workspace::factory(),
            'host_id' => User::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->optional()->paragraph(),
            'starts_at' => $starts,
            'ends_at' => $ends,
            'status' => 'scheduled',
        ];
    }
}
