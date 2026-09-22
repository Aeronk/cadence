<?php

namespace App\Notifications;

use App\Enums\WorkspaceRole;
use App\Models\WorkspaceInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WorkspaceInvited extends Notification
{
    use Queueable;

    /**
     * @param  string  $acceptUrl  Built by the caller, because the plaintext token
     *                             exists only on the instance that minted it.
     */
    public function __construct(
        public WorkspaceInvitation $invitation,
        public string $acceptUrl,
        public bool $isResend = false,
    ) {}

    public function via(object $notifiable): array
    {
        // Invitees often have no account yet, so they arrive as an anonymous
        // notifiable with nowhere to store a database notification.
        if ($notifiable instanceof AnonymousNotifiable) {
            return ['mail'];
        }

        return array_values(array_filter(['database', 'mail'], fn ($channel) => method_exists($notifiable, 'wantsNotification')
            ? $notifiable->wantsNotification('workspace_invited', $channel)
            : true,
        ));
    }

    public function toMail(object $notifiable): MailMessage
    {
        $workspace = $this->invitation->workspace;
        $inviter = $this->invitation->invitedBy;
        $role = $this->invitation->role;

        $message = (new MailMessage)
            ->subject($this->isResend
                ? "Reminder: you're invited to {$workspace->name}"
                : "You're invited to {$workspace->name}");

        if ($inviter) {
            $message->line("{$inviter->name} invited you to join the \"{$workspace->name}\" workspace on Cadence.");
        } else {
            $message->line("You've been invited to join the \"{$workspace->name}\" workspace on Cadence.");
        }

        if ($role === WorkspaceRole::Viewer) {
            $message->line('You will join as a viewer, which means you can see the workspace but not change anything in it.');
        } else {
            $message->line("You will join as {$role->label()}.");
        }

        return $message
            ->action('Accept invitation', $this->acceptUrl)
            ->line('This invitation expires on '.$this->invitation->expires_at->toFormattedDayDateString().'.')
            ->line('If you were not expecting this, you can ignore this email.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'invitation_id' => $this->invitation->id,
            'workspace_id' => $this->invitation->workspace_id,
            'workspace_name' => $this->invitation->workspace->name,
            'role' => $this->invitation->role->value,
            'invited_by_id' => $this->invitation->invited_by_id,
            'invited_by_name' => $this->invitation->invitedBy?->name,
            'expires_at' => $this->invitation->expires_at->toIso8601String(),
        ];
    }
}
