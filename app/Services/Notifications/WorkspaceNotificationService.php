<?php

namespace App\Services\Notifications;

use App\Models\Workspace;
use App\Notifications\AccountNotification;

class WorkspaceNotificationService
{
    public function notifyManagers(
        Workspace $workspace,
        string $category,
        string $title,
        string $body,
        string $targetType,
        int $targetId,
    ): void {
        $recipients = $workspace->members()
            ->wherePivotIn('role', ['owner', 'admin'])
            ->where('users.is_suspended', false)
            ->get();

        foreach ($recipients as $recipient) {
            $recipient->notify(new AccountNotification(
                category: $category,
                title: $title,
                body: $body,
                workspaceId: (int) $workspace->getKey(),
                targetType: $targetType,
                targetId: $targetId,
            ));
        }
    }
}
