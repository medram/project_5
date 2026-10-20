<?php

namespace App\Services\Links;

use App\Models\Link;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class LinkAccessUnlocks
{
    private const LIFETIME_SECONDS = 28_800;

    public function allows(Request $request, Link $link): bool
    {
        $unlock = $request->session()->get($this->sessionKey($link));

        if (! is_array($unlock)) {
            return false;
        }

        $isValid = ($unlock['version'] ?? null) === $link->access_code_version
            && is_int($unlock['expires_at'] ?? null)
            && $unlock['expires_at'] > now()->getTimestamp();

        if (! $isValid) {
            $request->session()->forget($this->sessionKey($link));
        }

        return $isValid;
    }

    public function grant(Request $request, Link $link): void
    {
        $request->session()->put($this->sessionKey($link), [
            'version' => $link->access_code_version,
            'expires_at' => now()->addSeconds(self::LIFETIME_SECONDS)->getTimestamp(),
        ]);
    }

    public function tooManyFailures(Request $request, Link $link): bool
    {
        return RateLimiter::tooManyAttempts($this->limiterKey($request, $link), 5);
    }

    public function recordFailure(Request $request, Link $link): void
    {
        RateLimiter::hit($this->limiterKey($request, $link), 300);
    }

    public function clearFailures(Request $request, Link $link): void
    {
        RateLimiter::clear($this->limiterKey($request, $link));
    }

    private function sessionKey(Link $link): string
    {
        return "unlocked-links.{$link->id}";
    }

    private function limiterKey(Request $request, Link $link): string
    {
        $visitor = hash_hmac(
            'sha256',
            "{$request->ip()}\n{$request->userAgent()}",
            (string) config('app.key'),
        );

        return "link-access:{$link->id}:{$visitor}";
    }
}
