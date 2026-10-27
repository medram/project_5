<?php

namespace App\Policies;

use App\Models\Link;
use App\Models\User;

class LinkPolicy
{
    public function view(User $user, Link $link): bool
    {
        return $user->belongsToWorkspace($link->workspace);
    }

    public function update(User $user, Link $link): bool
    {
        return $user->belongsToWorkspace($link->workspace);
    }

    public function delete(User $user, Link $link): bool
    {
        return $user->belongsToWorkspace($link->workspace);
    }
}
