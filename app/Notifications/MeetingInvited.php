<?php

namespace App\Notifications;

use App\Models\Meeting;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MeetingInvited extends Notification
{
    use Queueable;

    public function __construct(
        public Meeting $meeting,
        public User $invitedBy,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast', 'mail'];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Meeting invite: {$this->meeting->title}")
            ->line("{$this->invitedBy->name} invited you to a meeting.")
            ->line('Starts: '.$this->meeting->starts_at->toDayDateTimeString())
            ->action('View meeting', url('/meetings/'.$this->meeting->id));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'meeting_id' => $this->meeting->id,
            'meeting_title' => $this->meeting->title,
            'starts_at' => $this->meeting->starts_at?->toIso8601String(),
            'invited_by_id' => $this->invitedBy->id,
            'invited_by_name' => $this->invitedBy->name,
        ];
    }
}
