<?php

namespace App\Policies;

use App\Models\LinkPage;
use App\Models\User;

class LinkPagePolicy
{
    public function view(User $user, LinkPage $linkPage): bool
    {
        return $linkPage->workspace !== null && $user->can('manageContent', $linkPage->workspace);
    }

    public function viewLeads(User $user, LinkPage $linkPage): bool
    {
        return $linkPage->workspace !== null && $user->can('manageMembers', $linkPage->workspace);
    }

    public function update(User $user, LinkPage $linkPage): bool
    {
        return $this->view($user, $linkPage);
    }

    public function delete(User $user, LinkPage $linkPage): bool
    {
        return $this->view($user, $linkPage);
    }
}
