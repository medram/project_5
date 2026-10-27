<?php

namespace App\Notifications;

use DateTimeInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WorkspaceInvitationNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $workspaceName,
        public readonly string $inviterName,
        public readonly string $role,
        public readonly string $url,
        public readonly DateTimeInterface $expiresAt,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("You're invited to {$this->workspaceName}")
            ->greeting('You have a LinkPilot workspace invitation')
            ->line("{$this->inviterName} invited you to join {$this->workspaceName} as a {$this->role}.")
            ->action('Review invitation', $this->url)
            ->line('This invitation expires on '.$this->expiresAt->format('F j, Y').'.');
    }
}
