<?php

namespace Database\Factories;

use App\Enums\IntegrationProvider;
use App\Models\IntegrationAccount;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IntegrationAccount>
 */
class IntegrationAccountFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'workspace_id' => Workspace::factory(),
            'provider' => fake()->randomElement(IntegrationProvider::cases())->value,
            'external_account_id' => fake()->uuid(),
            'display_name' => fake()->email(),
            'access_token' => 'fake-access-token',
            'refresh_token' => 'fake-refresh-token',
            'token_expires_at' => now()->addHour(),
            'scopes' => ['read', 'write'],
            'status' => 'active',
        ];
    }

    public function expired(): static
    {
        return $this->state(fn () => ['token_expires_at' => now()->subMinute()]);
    }

    public function provider(IntegrationProvider $provider): static
    {
        return $this->state(fn () => ['provider' => $provider->value]);
    }
}
