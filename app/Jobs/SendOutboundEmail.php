<?php

namespace App\Jobs;

use App\Integrations\IntegrationManager;
use App\Models\IntegrationAccount;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendOutboundEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $backoff = 60;

    public function __construct(
        public int $integrationAccountId,
        public array $payload,
    ) {}

    public function handle(IntegrationManager $manager): void
    {
        $account = IntegrationAccount::query()->findOrFail($this->integrationAccountId);

        $manager->email($account)->send($account, $this->payload);
    }
}
