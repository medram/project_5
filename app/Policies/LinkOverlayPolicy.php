<?php

namespace App\Policies;

use App\Models\LinkOverlay;
use App\Models\User;

class LinkOverlayPolicy
{
    public function view(User $user, LinkOverlay $linkOverlay): bool
    {
        return $user->is($linkOverlay->user);
    }

    public function update(User $user, LinkOverlay $linkOverlay): bool
    {
        return $user->is($linkOverlay->user);
    }

    public function delete(User $user, LinkOverlay $linkOverlay): bool
    {
        return $user->is($linkOverlay->user);
    }
}
