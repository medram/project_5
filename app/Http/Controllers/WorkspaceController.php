<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Services\Billing\WorkspaceUsage;
use App\Services\Workspaces\WorkspaceContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceController
{
    public function index(Request $request, WorkspaceContext $context, WorkspaceUsage $usage): Response
    {
        /** @var User $user */
        $user = $request->user();
        $shared = $context->sharedFor($user);
        $current = Workspace::query()->findOrFail($shared['current']['id']);
        $canManageMembers = $user->can('manageMembers', $current);
        $members = $current->members()
            ->select(['users.id', 'users.name', 'users.email'])
            ->orderBy('users.name')
            ->get()
            ->map(fn (User $member): array => [
                'id' => (int) $member->getKey(),
                'name' => $member->name,
                'email' => $member->email,
                'role' => $member->pivot->role,
                'joined_at' => $member->pivot->created_at?->toIso8601String(),
            ])
            ->values();
        $invitations = $canManageMembers ? $current->invitations()
            ->whereNull('accepted_at')
            ->orderByDesc('created_at')
            ->get(['id', 'email', 'role', 'expires_at', 'revoked_at', 'sent_at'])
            ->map(fn (WorkspaceInvitation $invitation): array => [
                'id' => (int) $invitation->getKey(),
                'email' => $invitation->email,
                'role' => $invitation->role,
                'expires_at' => $invitation->expires_at->toIso8601String(),
                'sent_at' => $invitation->sent_at?->toIso8601String(),
                'status' => $invitation->revoked_at !== null
                    ? 'revoked'
                    : ($invitation->expires_at->isFuture() ? 'pending' : 'expired'),
            ])->values() : null;

        return Inertia::render('settings/workspaces/index', [
            'workspaces' => $shared['memberships'],
            'members' => $members,
            ...($canManageMembers ? ['invitations' => $invitations] : []),
            'canManageMembers' => $canManageMembers,
            'canTransferOwnership' => $user->can('transferOwnership', $current) && $members->count() > 1,
            'memberCapacity' => $usage->memberCapacityFor($current),
            'flash' => [
                'success' => $request->session()->get('success'),
                'invitationUrl' => $request->session()->get('invitationUrl'),
            ],
        ]);
    }

    public function store(Request $request, WorkspaceContext $context): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $workspace = DB::transaction(function () use ($user, $validated, $context): Workspace {
            $workspace = Workspace::query()->create([
                'name' => trim($validated['name']),
                'slug' => mb_substr(Str::slug(trim($validated['name'])) ?: 'workspace', 0, 70).'-'.Str::lower(Str::random(8)),
                'owner_id' => $user->id,
            ]);
            $workspace->members()->attach($user->id, ['role' => 'owner']);
            $context->activate($user, $workspace);

            return $workspace;
        });

        return to_route('account-settings.workspaces')->with('success', "{$workspace->name} workspace created.");
    }

    public function switch(Request $request, Workspace $workspace, WorkspaceContext $context): RedirectResponse
    {
        $context->activate($request->user(), $workspace);

        return to_route('links.index')->with('success', "Switched to {$workspace->name}.");
    }
}
