<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\AccountNotification;
use App\Services\Notifications\NotificationVisibility;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class NotificationCenterController extends Controller
{
    public function index(Request $request, NotificationVisibility $visibility): Response
    {
        $filter = $request->validate([
            'filter' => ['nullable', Rule::in(['all', 'unread'])],
        ])['filter'] ?? 'all';

        /** @var User $user */
        $user = $request->user();
        $query = $visibility->query($user);

        if ($filter === 'unread') {
            $query->whereNull('read_at');
        }

        $paginator = $query->latest()->paginate(25)->withQueryString();
        $notifications = $paginator->getCollection()
            ->filter(fn (DatabaseNotification $notification): bool => $visibility->canView($user, $notification))
            ->map(fn (DatabaseNotification $notification): array => $visibility->present($notification))
            ->values();
        $storedPreferences = $user->notificationPreferences()
            ->whereIn('category', array_keys(AccountNotification::CATEGORIES))
            ->pluck('email_enabled', 'category');

        return Inertia::render('notifications/index', [
            'filter' => $filter,
            'notifications' => $notifications,
            'unreadCount' => $visibility->unreadCount($user),
            'pagination' => [
                'currentPage' => $paginator->currentPage(),
                'lastPage' => $paginator->lastPage(),
            ],
            'emailPreferences' => collect(AccountNotification::CATEGORIES)
                ->map(fn (string $label, string $category): array => [
                    'category' => $category,
                    'label' => $label,
                    'enabled' => (bool) ($storedPreferences[$category] ?? false),
                ])
                ->values()
                ->all(),
        ]);
    }

    public function read(Request $request, string $notification): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $record = $user->notifications()->whereKey($notification)->firstOrFail();
        $record->markAsRead();

        return back();
    }

    public function readAll(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->unreadNotifications()->update(['read_at' => now()]);

        return back();
    }

    public function updatePreferences(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'emailPreferences' => ['required', 'array:'.implode(',', array_keys(AccountNotification::CATEGORIES))],
            'emailPreferences.*' => ['required', 'boolean'],
        ]);

        /** @var User $user */
        $user = $request->user();

        foreach ($validated['emailPreferences'] as $category => $enabled) {
            $user->notificationPreferences()->updateOrCreate(
                ['category' => $category],
                ['email_enabled' => (bool) $enabled],
            );
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Notification preferences updated.')]);

        return back();
    }
}
