<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 *
 * @method Client create($attributes = [], ?\Illuminate\Database\Eloquent\Model $parent = null)
 * @method Client createOne($attributes = [])
 */
class ClientFactory extends Factory
{
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'created_by' => User::factory(),
            'name' => fake()->name(),
            'company' => fake()->optional()->company(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->optional()->phoneNumber(),
            'website' => fake()->optional()->url(),
            'notes' => fake()->optional()->paragraph(),
        ];
    }
}
