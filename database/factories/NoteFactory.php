<?php

namespace Database\Factories;

use App\Models\Note;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Note>
 */
class NoteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'user_id' => User::factory(),
            'title' => fake()->sentence(3),
            'body' => fake()->paragraphs(2, true),
            'color' => fake()->randomElement(['yellow', 'green', 'blue', 'pink', 'gray']),
            'is_pinned' => false,
        ];
    }
}
