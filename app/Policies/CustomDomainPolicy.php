<?php

namespace App\Policies;

use App\Models\CustomDomain;
use App\Models\User;

class CustomDomainPolicy
{
    public function view(User $user, CustomDomain $customDomain): bool
    {
        return $user->is($customDomain->user);
    }

    public function update(User $user, CustomDomain $customDomain): bool
    {
        return $user->is($customDomain->user);
    }

    public function delete(User $user, CustomDomain $customDomain): bool
    {
        return $user->is($customDomain->user);
    }
}
