<?php

namespace App\Policies;

use App\Models\TrackingPixel;
use App\Models\User;

class TrackingPixelPolicy
{
    public function view(User $user, TrackingPixel $trackingPixel): bool
    {
        return $user->is($trackingPixel->user);
    }

    public function update(User $user, TrackingPixel $trackingPixel): bool
    {
        return $user->is($trackingPixel->user);
    }

    public function delete(User $user, TrackingPixel $trackingPixel): bool
    {
        return $user->is($trackingPixel->user);
    }
}
