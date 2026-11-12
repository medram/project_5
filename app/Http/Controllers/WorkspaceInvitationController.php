<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Services\Workspaces\WorkspaceInvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\JsonResponse;

class WorkspaceInvitationController
{
    public function index(Workspace $workspace): JsonResponse
    {
        Gate::authorize('manageMembers', $workspace);
        $invitations = $workspace->invitations()
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
            ]);

        return response()->json(['data' => $invitations]);
    }

    public function store(Request $request, Workspace $workspace, WorkspaceInvitationService $invitations): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'role' => ['required', 'string', 'in:admin,member'],
        ]);

        /** @var User $actor */
        $actor = $request->user();
        $issued = $invitations->issue($actor, $workspace, $validated['email'], $validated['role']);
        $url = $invitations->deliver($actor, $workspace, $issued['invitation'], $issued['token']);

        return back()->with('success', "Invitation sent to {$issued['invitation']->email}.")
            ->with('invitationUrl', $url);
    }

    public function resend(
        Request $request,
        Workspace $workspace,
        WorkspaceInvitation $invitation,
        WorkspaceInvitationService $invitations,
    ): RedirectResponse {
        /** @var User $actor */
        $actor = $request->user();
        $issued = $invitations->resend($actor, $workspace, $invitation);
        $url = $invitations->deliver($actor, $workspace, $issued['invitation'], $issued['token']);

        return back()->with('success', "Invitation resent to {$issued['invitation']->email}.")
            ->with('invitationUrl', $url);
    }

    public function destroy(
        Request $request,
        Workspace $workspace,
        WorkspaceInvitation $invitation,
        WorkspaceInvitationService $invitations,
    ): RedirectResponse {
        $invitations->revoke($request->user(), $workspace, $invitation);

        return back()->with('success', 'Invitation revoked.');
    }
}
