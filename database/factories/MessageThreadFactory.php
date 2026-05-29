<?php

namespace Database\Factories;

use App\Enums\MessageChannel;
use App\Models\IntegrationAccount;
use App\Models\MessageThread;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MessageThread>
 */
class MessageThreadFactory extends Factory
{
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'integration_account_id' => IntegrationAccount::factory(),
            'channel' => MessageChannel::Email->value,
            'external_thread_id' => fake()->uuid(),
            'subject' => fake()->sentence(),
            'participants' => [fake()->email(), fake()->email()],
            'last_message_at' => now(),
        ];
    }
}
