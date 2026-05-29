<?php

namespace App\Providers;

use App\Enums\IntegrationProvider;
use App\Integrations\IntegrationManager;
use App\Integrations\Providers\Gmail\GmailProvider;
use App\Integrations\Providers\Microsoft\MicrosoftProvider;
use Illuminate\Support\ServiceProvider;

class IntegrationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(IntegrationManager::class, function () {
            $manager = new IntegrationManager;

            $manager->bind(IntegrationProvider::Gmail, GmailProvider::class);
            $manager->bind(IntegrationProvider::Microsoft, MicrosoftProvider::class);

            return $manager;
        });
    }
}
