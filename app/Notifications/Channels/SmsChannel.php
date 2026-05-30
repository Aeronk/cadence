<?php

namespace App\Notifications\Channels;

use App\Enums\IntegrationProvider;
use App\Integrations\IntegrationManager;
use App\Models\IntegrationAccount;
use Illuminate\Notifications\Notification;

class SmsChannel
{
    public function __construct(protected IntegrationManager $manager) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toSms')) {
            return;
        }

        $payload = $notification->toSms($notifiable);
        $to = is_array($payload) ? ($payload['to'] ?? null) : null;
        $body = is_array($payload) ? ($payload['body'] ?? '') : (string) $payload;

        $to = $to ?: $this->phoneFor($notifiable);
        if (! $to || ! $body) {
            return;
        }

        $account = $this->account();
        if (! $account) {
            return;
        }

        $this->manager->messaging($account)->send($account, [
            'to' => $to,
            'body_text' => $body,
        ]);
    }

    protected function phoneFor(object $notifiable): ?string
    {
        if (method_exists($notifiable, 'routeNotificationFor')) {
            $route = $notifiable->routeNotificationFor('sms');
            if ($route) {
                return (string) $route;
            }
        }

        return $notifiable->phone ?? null;
    }

    protected function account(): ?IntegrationAccount
    {
        return IntegrationAccount::query()
            ->where('provider', IntegrationProvider::TwilioSms->value)
            ->where('status', 'active')
            ->first();
    }
}
