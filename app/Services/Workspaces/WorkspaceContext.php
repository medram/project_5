<?php

namespace App\Services\Workspaces;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class WorkspaceContext
{
    public function __construct(private readonly CreatesPersonalWorkspace $createsPersonalWorkspace) {}

    public function currentFor(User $user): Workspace
    {
        $current = $user->currentWorkspace()->first();

        if ($current !== null && $user->belongsToWorkspace($current)) {
            return $current;
        }

        $personal = $user->personalWorkspace()->first();

        if ($personal !== null) {
            $this->createsPersonalWorkspace->for($user);
            $personal = $user->personalWorkspace()->firstOrFail();
            $this->activate($user, $personal);

            return $personal;
        }

        $membership = $user->workspaces()->orderBy('workspaces.id')->first();

        if ($membership !== null) {
            $this->activate($user, $membership);

            return $membership;
        }

        $personal = $this->createsPersonalWorkspace->for($user);
        $this->activate($user, $personal);

        return $personal;
    }

    public function activate(User $user, Workspace $workspace): Workspace
    {
        if (! $user->belongsToWorkspace($workspace)) {
            throw new AccessDeniedHttpException;
        }

        if ((int) $user->current_workspace_id !== (int) $workspace->getKey()) {
            $user->forceFill(['current_workspace_id' => $workspace->getKey()])->save();
        }

        return $workspace;
    }

    /** @return array{current: array{id: int, name: string, slug: string, role: string}, memberships: Collection<int, array{id: int, name: string, slug: string, role: string}>} */
    public function sharedFor(User $user): array
    {
        $current = $this->currentFor($user);
        $memberships = $user->workspaces()
            ->select(['workspaces.id', 'workspaces.name', 'workspaces.slug'])
            ->orderBy('workspaces.name')
            ->get()
            ->map(fn (Workspace $workspace): array => [
                'id' => (int) $workspace->getKey(),
                'name' => $workspace->name,
                'slug' => $workspace->slug,
                'role' => $workspace->pivot->role,
            ]);

        return [
            'current' => [
                'id' => (int) $current->getKey(),
                'name' => $current->name,
                'slug' => $current->slug,
                'role' => (string) $memberships->firstWhere('id', (int) $current->getKey())['role'],
            ],
            'memberships' => $memberships,
        ];
    }
}
