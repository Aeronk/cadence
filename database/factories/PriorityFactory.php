<?php

namespace Database\Factories;

use App\Models\Priority;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Priority>
 */
class PriorityFactory extends Factory
{
    public function definition(): array
    {
        $level = fake()->numberBetween(1, 4);

        return [
            'workspace_id' => Workspace::factory(),
            'name' => ['Low', 'Medium', 'High', 'Urgent'][$level - 1],
            'color' => ['gray', 'blue', 'orange', 'red'][$level - 1],
            'level' => $level,
            'is_default' => false,
        ];
    }
}
