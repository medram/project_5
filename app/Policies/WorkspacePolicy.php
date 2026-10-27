<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Workspace;

class WorkspacePolicy
{
    public function view(User $user, Workspace $workspace): bool
    {
        return $this->roleFor($user, $workspace) !== null;
    }

    public function manageContent(User $user, Workspace $workspace): bool
    {
        return $this->view($user, $workspace);
    }

    public function manageIntegrations(User $user, Workspace $workspace): bool
    {
        return in_array($this->roleFor($user, $workspace), ['owner', 'admin'], true);
    }

    public function selectIntegrations(User $user, Workspace $workspace): bool
    {
        return $this->view($user, $workspace);
    }

    public function manageMembers(User $user, Workspace $workspace): bool
    {
        return in_array($this->roleFor($user, $workspace), ['owner', 'admin'], true);
    }

    public function manageLinkApprovals(User $user, Workspace $workspace): bool
    {
        return $this->manageMembers($user, $workspace);
    }

    public function manageBilling(User $user, Workspace $workspace): bool
    {
        return $this->roleFor($user, $workspace) === 'owner';
    }

    public function viewBilling(User $user, Workspace $workspace): bool
    {
        return $this->view($user, $workspace);
    }

    public function transferOwnership(User $user, Workspace $workspace): bool
    {
        return $this->roleFor($user, $workspace) === 'owner';
    }

    private function roleFor(User $user, Workspace $workspace): ?string
    {
        return \Illuminate\Support\Facades\DB::table('workspace_user')
            ->where('workspace_id', $workspace->getKey())
            ->where('user_id', $user->getKey())
            ->value('role');
    }
}
