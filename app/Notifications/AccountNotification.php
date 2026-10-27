<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountNotification extends Notification
{
    public const CATEGORIES = [
        'invitations' => 'Workspace invitations',
        'domains' => 'Domain verification failures',
        'webhooks' => 'Webhook delivery failures',
        'usage' => 'Usage thresholds',
        'billing' => 'Billing and plan changes',
        'admin' => 'Platform announcements',
    ];

    public function __construct(
        public readonly string $category,
        public readonly string $title,
        public readonly string $body,
        public readonly ?int $workspaceId = null,
        public readonly ?string $targetType = null,
        public readonly ?int $targetId = null,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        $channels = ['database'];
        $mailer = config('mail.default');

        if (
            $notifiable instanceof User
            && is_string($mailer)
            && $mailer !== ''
            && ! in_array($mailer, ['log', 'array'], true)
            && $notifiable->notificationPreferences()
                ->where('category', $this->category)
                ->where('email_enabled', true)
                ->exists()
        ) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /** @return array<string, int|string|null> */
    public function toDatabase(object $notifiable): array
    {
        return [
            'category' => $this->category,
            'title' => $this->title,
            'body' => $this->body,
            'workspace_id' => $this->workspaceId,
            'target_type' => $this->targetType,
            'target_id' => $this->targetId,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title)
            ->line($this->body)
            ->action('View notifications', route('notifications.index'));
    }
}
