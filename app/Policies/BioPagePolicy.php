<?php

namespace App\Policies;

use App\Models\BioPage;
use App\Models\User;

class BioPagePolicy
{
    public function view(User $user, BioPage $bioPage): bool
    {
        return $bioPage->workspace !== null && $user->can('manageContent', $bioPage->workspace);
    }

    public function update(User $user, BioPage $bioPage): bool
    {
        return $this->view($user, $bioPage);
    }

    public function delete(User $user, BioPage $bioPage): bool
    {
        return $this->view($user, $bioPage);
    }
}
