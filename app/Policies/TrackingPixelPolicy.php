<?php

namespace App\Policies;

use App\Models\TrackingPixel;
use App\Models\User;

class TrackingPixelPolicy
{
    public function view(User $user, TrackingPixel $trackingPixel): bool
    {
        return $trackingPixel->workspace !== null && $user->can('view', $trackingPixel->workspace);
    }

    public function update(User $user, TrackingPixel $trackingPixel): bool
    {
        return $trackingPixel->workspace !== null && $user->can('manageIntegrations', $trackingPixel->workspace);
    }

    public function delete(User $user, TrackingPixel $trackingPixel): bool
    {
        return $trackingPixel->workspace !== null && $user->can('manageIntegrations', $trackingPixel->workspace);
    }
}
