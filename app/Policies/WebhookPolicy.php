<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Webhook;

class WebhookPolicy
{
    public function view(User $user, Webhook $webhook): bool
    {
        return $user->is($webhook->user);
    }

    public function update(User $user, Webhook $webhook): bool
    {
        return $user->is($webhook->user);
    }

    public function delete(User $user, Webhook $webhook): bool
    {
        return $user->is($webhook->user);
    }
}
