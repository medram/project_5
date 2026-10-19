<?php

namespace App\Policies;

use App\Models\BioPage;
use App\Models\User;

class BioPagePolicy
{
    public function view(User $user, BioPage $bioPage): bool
    {
        return $user->is($bioPage->user);
    }

    public function update(User $user, BioPage $bioPage): bool
    {
        return $user->is($bioPage->user);
    }

    public function delete(User $user, BioPage $bioPage): bool
    {
        return $user->is($bioPage->user);
    }
}
