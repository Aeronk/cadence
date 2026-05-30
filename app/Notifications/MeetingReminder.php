<?php

namespace App\Notifications;

use App\Models\Meeting;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MeetingReminder extends Notification
{
    use Queueable;

    public function __construct(public Meeting $meeting) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $when = $this->meeting->starts_at->toDayDateTimeString();
        $line = match ($this->meeting->meeting_type) {
            Meeting::TYPE_PHYSICAL => "Location: {$this->meeting->location}",
            Meeting::TYPE_HYBRID => "Hybrid \u{2014} {$this->meeting->location} / {$this->meeting->meeting_url}",
            default => "Join: {$this->meeting->meeting_url}",
        };

        return (new MailMessage)
            ->subject("Reminder: {$this->meeting->title}")
            ->line("Starts at {$when}.")
            ->line($line)
            ->action('Open meeting', url('/meetings/'.$this->meeting->id));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'meeting_reminder',
            'meeting_id' => $this->meeting->id,
            'meeting_title' => $this->meeting->title,
            'starts_at' => $this->meeting->starts_at->toIso8601String(),
            'meeting_type' => $this->meeting->meeting_type,
            'channel' => $this->meeting->channel,
        ];
    }

    public function toSms(object $notifiable): array
    {
        return [
            'body' => "Reminder: {$this->meeting->title} starts at "
                .$this->meeting->starts_at->format('g:i A').".",
        ];
    }

    public function toWhatsApp(object $notifiable): array
    {
        return $this->toSms($notifiable);
    }
}
