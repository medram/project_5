<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Webhook;

class WebhookPolicy
{
    public function view(User $user, Webhook $webhook): bool
    {
        return app(WorkspacePolicy::class)->manageIntegrations($user, $webhook->workspace);
    }

    public function update(User $user, Webhook $webhook): bool
    {
        return app(WorkspacePolicy::class)->manageIntegrations($user, $webhook->workspace);
    }

    public function delete(User $user, Webhook $webhook): bool
    {
        return $this->update($user, $webhook);
    }
}
