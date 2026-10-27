<?php

namespace App\Http\Middleware;

use App\Models\Workspace;
use App\Services\Billing\WorkspaceUsage;
use App\Services\Workspaces\WorkspaceContext;
use App\Services\Notifications\NotificationVisibility;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $notificationData = null;
        $loadNotificationData = function () use ($request, &$notificationData): array {
            if ($notificationData !== null) {
                return $notificationData;
            }

            $user = $request->user();

            if ($user === null || $user->is_suspended) {
                return $notificationData = ['unreadCount' => 0, 'preview' => []];
            }

            $visibility = app(NotificationVisibility::class);

            return $notificationData = [
                'unreadCount' => $visibility->unreadCount($user),
                'preview' => $visibility->recent($user),
            ];
        };
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
                'isAdmin' => (bool) $request->user()?->is_admin,
                'canSearchPlatform' => $request->user() !== null && $request->user()->can('admin.search'),
            ],
            'notificationUnreadCount' => fn (): int => $loadNotificationData()['unreadCount'],
            'notificationPreview' => fn (): array => $loadNotificationData()['preview'],
            'workspace' => function () use ($request): ?array {
                $user = $request->user();

                if ($user === null) {
                    return null;
                }

                $workspace = app(WorkspaceContext::class)->sharedFor($user);
                $current = Workspace::query()->findOrFail($workspace['current']['id']);
                $usage = app(WorkspaceUsage::class);
                $workspace['current']['plan'] = $usage->planFor($current)->name;
                $workspace['current']['usage'] = $usage->for($current);

                return $workspace;
            },
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
