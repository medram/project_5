<?php

namespace App\Services\Accounts;

use App\Services\Settings\PlatformSettings;
use Illuminate\Validation\ValidationException;

class RegistrationPolicy
{
    public function __construct(private readonly PlatformSettings $settings) {}

    public function enabled(): bool
    {
        return (bool) ($this->settings->resolved()['authentication']['registration_enabled'] ?? true);
    }

    public function googleRequiresExisting(): bool
    {
        return (bool) ($this->settings->resolved()['authentication']['google_require_existing'] ?? false);
    }

    public function assertEmailAllowed(string $email): void
    {
        $blocked = (string) ($this->settings->resolved()['authentication']['blocked_email_domains'] ?? '');
        $domain = strtolower((string) substr(strrchr($email, '@') ?: '', 1));
        $blockedDomains = array_filter(array_map('trim', preg_split('/\R/', $blocked) ?: []));

        if ($domain !== '' && in_array($domain, array_map('strtolower', $blockedDomains), true)) {
            throw ValidationException::withMessages([
                'email' => 'This email domain is not allowed for registration.',
            ]);
        }
    }
}
