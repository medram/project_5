<?php

namespace App\Services\Analytics;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Cookie as SymfonyCookie;

class TrackingConsent
{
    public const COOKIE_NAME = 'linkpilot_tracking_consent';

    public const GRANTED = 'granted';

    public const DENIED = 'denied';

    public function allows(Request $request): bool
    {
        return $this->choice($request) === self::GRANTED;
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
