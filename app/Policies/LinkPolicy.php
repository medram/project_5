<?php

namespace App\Policies;

use App\Models\Link;
use App\Models\User;

class LinkPolicy
{
    public function view(User $user, Link $link): bool
    {
        return $user->is($link->user);
    }

    public function update(User $user, Link $link): bool
    {
        return $user->is($link->user);
    }

    public function delete(User $user, Link $link): bool
    {
        return $user->is($link->user);
    }
}
