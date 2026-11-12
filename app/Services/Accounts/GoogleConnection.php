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
        $enabled = $settings->hasStored('authentication', 'google_enabled')
            ? (bool) ($settings->resolved()['authentication']['google_enabled'] ?? false)
            : (bool) config('linkpilot.auth.google_enabled');

        return $enabled
            && filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'));
    }

    public function accountFor(User $user): ?SocialAccount
    {
        return $user->socialAccounts()->where('provider', 'google')->first();
    }
}
