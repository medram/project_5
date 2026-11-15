<?php

namespace App\Services\Accounts;

use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Settings\PlatformSettings;

final class GoogleConnection
{
    public function enabled(): bool
    {
        $settings = app(PlatformSettings::class);
        $authentication = $settings->resolved()['authentication'] ?? [];

        return (bool) ($authentication['google_enabled'] ?? false)
            && filled($authentication['google_client_id'] ?? null)
            && $settings->secret('authentication', 'google_client_secret') !== null;
    }

    public function accountFor(User $user): ?SocialAccount
    {
        return $user->socialAccounts()->where('provider', 'google')->first();
    }
}
