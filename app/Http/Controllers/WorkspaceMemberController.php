<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Workspace;
use App\Services\Workspaces\WorkspaceMembershipService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WorkspaceMemberController
{
    public function update(
        Request $request,
        Workspace $workspace,
        User $member,
        WorkspaceMembershipService $memberships,
    ): RedirectResponse {
        $validated = $request->validate([
            'role' => ['required', 'string', 'in:admin,member'],
        ]);

        $memberships->changeRole($request->user(), $workspace, $member, $validated['role']);

        return back()->with('success', 'Member role updated.');
    }

    public function destroy(
        Request $request,
        Workspace $workspace,
        User $member,
        WorkspaceMembershipService $memberships,
    ): RedirectResponse {
        $memberships->remove($request->user(), $workspace, $member);

        return back()->with('success', 'Member removed from workspace.');
    }
}
