<?php

namespace App\Jobs;

use App\Integrations\IntegrationManager;
use App\Models\IntegrationAccount;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SyncIntegrationAccountInbox implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(public int $integrationAccountId) {}

    public function handle(IntegrationManager $manager): void
    {
        $account = IntegrationAccount::query()->find($this->integrationAccountId);

        if (! $account || ! $account->isActive()) {
            return;
        }

        try {
            $manager->email($account)->syncInbox($account);
        } catch (Throwable $e) {
            $account->forceFill(['last_error' => $e->getMessage()])->save();
            throw $e;
        }
    }
}
