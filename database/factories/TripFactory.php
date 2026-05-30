<?php

namespace Database\Factories;

use App\Models\Trip;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Trip>
 *
 * @method Trip create($attributes = [], ?\Illuminate\Database\Eloquent\Model $parent = null)
 */
class TripFactory extends Factory
{
    public function definition(): array
    {
        $departs = fake()->dateTimeBetween('+1 week', '+1 month');
        $returns = (clone $departs)->modify('+5 days');

        return [
            'workspace_id' => Workspace::factory(),
            'user_id' => User::factory(),
            'name' => fake()->randomElement(['Nairobi field visit', 'Donor meetings DC', 'Conference London']),
            'purpose' => fake()->randomElement(['donor', 'fieldwork', 'conference', 'personal']),
            'destination_country' => fake()->countryCode(),
            'destination_city' => fake()->city(),
            'departs_at' => $departs,
            'returns_at' => $returns,
            'status' => 'planned',
        ];
    }
}
