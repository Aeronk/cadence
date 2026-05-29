<?php

namespace App\Providers;

use App\Integrations\IntegrationManager;
use Illuminate\Support\ServiceProvider;

class IntegrationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(IntegrationManager::class, function () {
            $manager = new IntegrationManager;

            // Concrete bindings land in M8-M10. Example:
            // $manager->bind(IntegrationProvider::Gmail, \App\Integrations\Providers\Gmail\GmailProvider::class);

            return $manager;
        });
    }
}
