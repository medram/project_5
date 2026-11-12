<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PlatformPermission;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\AccountNotification;
use App\Services\Platform\AdminAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdminNotificationController extends Controller
{
    public function __construct(private readonly AdminAudit $audit) {}

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:100'],
            'body' => ['required', 'string', 'max:2000'],
        ]);
        $title = trim($validated['title']);
        $body = trim($validated['body']);

        $recipients = User::query()->where('is_suspended', false)->count();

        User::query()
            ->where('is_suspended', false)
            ->orderBy('id')
            ->chunkById(500, function ($recipients) use ($title, $body): void {
                foreach ($recipients as $recipient) {
                    $recipient->notify(new AccountNotification(
                        category: 'admin',
                        title: $title,
                        body: $body,
                    ));
                }
            });

        $this->audit->record(
            $request->user(),
            PlatformPermission::AdminNotificationsBroadcast,
            'broadcast_notification',
            null,
            null,
            ['recipients' => $recipients],
        );

        return to_route('admin.index')->with('success', 'Announcement sent to active accounts.');
    }
}
