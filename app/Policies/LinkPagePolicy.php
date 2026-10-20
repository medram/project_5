<?php

namespace App\Policies;

use App\Models\LinkPage;
use App\Models\User;

class LinkPagePolicy
{
    public function view(User $user, LinkPage $linkPage): bool
    {
        return $user->is($linkPage->user);
    }

    public function update(User $user, LinkPage $linkPage): bool
    {
        return $user->is($linkPage->user);
    }

    public function delete(User $user, LinkPage $linkPage): bool
    {
        return $user->is($linkPage->user);
    }
}
