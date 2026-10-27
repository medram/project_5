<?php

namespace App\Policies;

use App\Models\CustomDomain;
use App\Models\User;

class CustomDomainPolicy
{
    public function view(User $user, CustomDomain $customDomain): bool
    {
        return app(WorkspacePolicy::class)->manageIntegrations($user, $customDomain->workspace);
    }

    public function update(User $user, CustomDomain $customDomain): bool
    {
        return app(WorkspacePolicy::class)->manageIntegrations($user, $customDomain->workspace);
    }

    public function delete(User $user, CustomDomain $customDomain): bool
    {
        return $this->update($user, $customDomain);
    }
}
