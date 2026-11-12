<?php

namespace App\Services\Accounts;

use App\Models\SocialAccount;
use App\Models\User;

final class GoogleConnection
{
    public function enabled(): bool
    {
        return (bool) config('linkpilot.auth.google_enabled')
            && filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'));
    }

    public function accountFor(User $user): ?SocialAccount
    {
        return $user->socialAccounts()->where('provider', 'google')->first();
    }
}