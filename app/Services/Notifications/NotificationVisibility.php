<?php

namespace App\Services\Notifications;

use App\Models\CustomDomain;
use App\Models\User;
use App\Models\Webhook;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;

class NotificationVisibility
{
    /**
     * @return MorphMany<DatabaseNotification, User>
     */
    public function query(User $user): MorphMany
    {
        $workspaceIds = DB::table('workspace_user')
            ->where('user_id', $user->getKey())
            ->whereIn('role', ['owner', 'admin'])
            ->pluck('workspace_id');

        return $user->notifications()->where(function ($query) use ($workspaceIds): void {
            $query->where(function ($query): void {
                $query->where('data->category', 'admin')
                    ->whereNull('data->workspace_id')
                    ->whereNull('data->target_type')
                    ->whereNull('data->target_id');
            });

            if ($workspaceIds->isNotEmpty()) {
                $query->orWhereIn('data->workspace_id', $workspaceIds->all());
            }
        });
    }

    public function canView(User $user, DatabaseNotification $notification): bool
    {
        $data = $notification->data;
        $category = $data['category'] ?? null;

        if ($category === 'admin') {
            return ! isset($data['workspace_id'])
                && ! isset($data['target_type'])
                && ! isset($data['target_id']);
        }

        $workspaceId = (int) ($data['workspace_id'] ?? 0);
        $targetId = (int) ($data['target_id'] ?? 0);
        $targetType = $data['target_type'] ?? null;

        if ($workspaceId < 1 || $targetId < 1) {
            return false;
        }

        $role = DB::table('workspace_user')
            ->where('workspace_id', $workspaceId)
            ->where('user_id', $user->getKey())
            ->value('role');

        if (! in_array($role, ['owner', 'admin'], true)) {
            return false;
        }

        return match ($category) {
            'invitations', 'usage', 'billing' => $targetType === 'workspace'
                && $targetId === $workspaceId
                && Workspace::query()->whereKey($workspaceId)->exists(),
            'domains' => $targetType === 'custom_domain'
                && CustomDomain::query()->whereKey($targetId)->where('workspace_id', $workspaceId)->exists(),
            'webhooks' => $targetType === 'webhook'
                && Webhook::query()->whereKey($targetId)->where('workspace_id', $workspaceId)->exists(),
            default => false,
        };
    }

    /**
     * @return array<int, array{id: string, category: string, title: string, body: string, createdAt: string|null, readAt: string|null, href: string}>
     */
    public function recent(User $user): array
    {
        return $this->query($user)
            ->latest()
            ->limit(20)
            ->get()
            ->filter(fn (DatabaseNotification $notification): bool => $this->canView($user, $notification))
            ->take(5)
            ->map(fn (DatabaseNotification $notification): array => $this->present($notification))
            ->values()
            ->all();
    }

    /**
     * @return array{id: string, category: string, title: string, body: string, createdAt: string|null, readAt: string|null, href: string}
     */
    public function present(DatabaseNotification $notification): array
    {
        return [
            'id' => (string) $notification->getKey(),
            'category' => $notification->data['category'],
            'title' => $notification->data['title'],
            'body' => $notification->data['body'],
            'createdAt' => $notification->created_at?->toIso8601String(),
            'readAt' => $notification->read_at?->toIso8601String(),
            'href' => ($notification->data['category'] ?? null) === 'admin'
                ? route('notifications.index')
                : route('account-settings.workspaces'),
        ];
    }

    public function unreadCount(User $user): int
    {
        return $this->query($user)
            ->whereNull('read_at')
            ->get()
            ->filter(fn (DatabaseNotification $notification): bool => $this->canView($user, $notification))
            ->count();
    }
}
