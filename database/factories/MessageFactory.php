<?php

namespace Database\Factories;

use App\Enums\MessageChannel;
use App\Models\IntegrationAccount;
use App\Models\Message;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'integration_account_id' => IntegrationAccount::factory(),
            'channel' => MessageChannel::Email->value,
            'direction' => Message::DIRECTION_INBOUND,
            'external_id' => fake()->uuid(),
            'from_address' => fake()->email(),
            'to_addresses' => [fake()->email()],
            'subject' => fake()->sentence(4),
            'body_text' => fake()->paragraph(),
            'status' => 'received',
            'sent_at' => now(),
        ];
    }

    public function outbound(): static
    {
        return $this->state(fn () => ['direction' => Message::DIRECTION_OUTBOUND, 'status' => 'sent']);
    }
}
