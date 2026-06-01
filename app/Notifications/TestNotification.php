<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class TestNotification extends Notification
{
    use Queueable;

    public function __construct(public string $message = 'This is a test notification from Cadence.') {}

    public function via(object $notifiable): array
    {
        return array_values(array_filter(['database', 'broadcast', 'mail'], fn ($channel) =>
            method_exists($notifiable, 'wantsNotification')
                ? $notifiable->wantsNotification('test', $channel)
                : in_array($channel, ['database', 'broadcast'], true)
        ));
    }

    public function toMail(object $notifiable): \Illuminate\Notifications\Messages\MailMessage
    {
        return (new \Illuminate\Notifications\Messages\MailMessage)
            ->subject('Cadence — test notification')
            ->line($this->message);
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'test',
            'title' => 'Test notification',
            'body' => $this->message,
            'sent_at' => now()->toIso8601String(),
        ];
    }
}
