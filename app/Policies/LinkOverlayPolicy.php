<?php

namespace App\Policies;

use App\Models\LinkOverlay;
use App\Models\User;

class LinkOverlayPolicy
{
    public function view(User $user, LinkOverlay $linkOverlay): bool
    {
        return $linkOverlay->workspace !== null && $user->can('view', $linkOverlay->workspace);
    }

    public function update(User $user, LinkOverlay $linkOverlay): bool
    {
        return $linkOverlay->workspace !== null && $user->can('manageIntegrations', $linkOverlay->workspace);
    }

    public function delete(User $user, LinkOverlay $linkOverlay): bool
    {
        return $linkOverlay->workspace !== null && $user->can('manageIntegrations', $linkOverlay->workspace);
    }
}
