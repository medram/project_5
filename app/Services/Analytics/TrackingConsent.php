<?php

namespace App\Services\Analytics;

use App\Services\Settings\PlatformSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Cookie as SymfonyCookie;

class TrackingConsent
{
    public const COOKIE_NAME = 'linkpilot_tracking_consent';

    public const GRANTED = 'granted';

    public const DENIED = 'denied';

    public function __construct(private readonly PlatformSettings $settings) {}

    public function allows(Request $request): bool
    {
        if (! $this->required()) {
            return $this->canGrant();
        }

        return $this->choice($request) === self::GRANTED;
    }

    public function required(): bool
    {
        return (bool) ($this->settings->resolved()['analytics']['consent_enabled'] ?? true);
    }

    public function effectiveChoice(Request $request): ?string
    {
        if (! $this->required()) {
            return $this->canGrant() ? self::GRANTED : null;
        }

        return $this->choice($request);
    }

    /**
     * Whether the privacy prerequisites for granting tracking consent are
     * configured: a public privacy notice URL plus the analytics hashing key
     * and its version.
     */
    public function canGrant(): bool
    {
        return filled(config('services.linkpilot.analytics.privacy_url'))
            && filled(config('services.linkpilot.analytics.hash_key'))
            && filled(config('services.linkpilot.analytics.hash_key_version'));
    }

    public function choice(Request $request): ?string
    {
        $choice = $request->cookie(self::COOKIE_NAME);

        return in_array($choice, [self::GRANTED, self::DENIED], true)
            ? $choice
            : null;
    }

    public function cookie(string $choice): SymfonyCookie
    {
        if (! in_array($choice, [self::GRANTED, self::DENIED], true)) {
            throw new InvalidArgumentException('Invalid tracking consent choice.');
        }

        return Cookie::make(
            self::COOKIE_NAME,
            $choice,
            60 * 24 * 180,
            null,
            null,
            null,
            true,
            false,
            'lax',
        );
    }
}
