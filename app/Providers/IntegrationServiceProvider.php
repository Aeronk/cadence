<?php

namespace App\Providers;

use App\Enums\IntegrationProvider;
use App\Integrations\IntegrationManager;
use App\Integrations\Providers\Gmail\GmailProvider;
use App\Integrations\Providers\Microsoft\MicrosoftProvider;
use App\Integrations\Providers\Twilio\TwilioProvider;
use App\Integrations\Providers\WhatsApp\WhatsAppCloudProvider;
use Illuminate\Support\ServiceProvider;

class IntegrationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(IntegrationManager::class, function () {
            $manager = new IntegrationManager;

            // One Google OAuth grant covers Gmail + Calendar.
            $manager->bind(IntegrationProvider::Gmail, GmailProvider::class);
            $manager->bind(IntegrationProvider::GoogleCalendar, GmailProvider::class);

            // Microsoft Graph: Outlook mail + calendar through one grant.
            $manager->bind(IntegrationProvider::Microsoft, MicrosoftProvider::class);

            // SMS + WhatsApp use shared business credentials (env-configured),
            // not per-user OAuth.
            $manager->bind(IntegrationProvider::TwilioSms, TwilioProvider::class);
            $manager->bind(IntegrationProvider::WhatsAppCloud, WhatsAppCloudProvider::class);

            return $manager;
        });
    }
}
