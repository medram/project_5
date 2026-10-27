<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Workspaces\WorkspaceInvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AcceptWorkspaceInvitationController
{
    public function show(Request $request, string $token, WorkspaceInvitationService $invitations): Response|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $invitation = $invitations->findForAcceptance($user, $token);

        if ($invitation->accepted_at !== null) {
            return to_route('account-settings.workspaces');
        }

        return Inertia::render('workspaces/accept', [
            'token' => $token,
            'invitation' => [
                'workspace_name' => $invitation->workspace->name,
                'email' => $invitation->email,
                'role' => $invitation->role,
                'expires_at' => $invitation->expires_at->toIso8601String(),
            ],
        ]);
    }

    public function accept(Request $request, string $token, WorkspaceInvitationService $invitations): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $workspace = $invitations->accept($user, $token);

        return to_route('account-settings.workspaces')->with('success', "You joined {$workspace->name}.");
    }
}
