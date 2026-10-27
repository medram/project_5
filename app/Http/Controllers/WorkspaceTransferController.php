<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use App\Services\Workspaces\WorkspaceTransferService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WorkspaceTransferController
{
    public function __invoke(Request $request, Workspace $workspace, WorkspaceTransferService $transfer): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'password' => ['required', 'string'],
        ]);

        $transfer->transfer($request->user(), $workspace, (int) $validated['user_id'], $validated['password']);

        return to_route('account-settings.workspaces')->with('success', 'Workspace ownership transferred.');
    }
}
