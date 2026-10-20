<?php

namespace App\Services\Links;

use App\Models\Link;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
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

    /**
     * Returns null when the visitor has exhausted the limit or an in-flight
     * attempt could not safely acquire the per-visitor lock.
     */
    public function attempt(Request $request, Link $link, string $accessCode): ?bool
    {
        $lock = Cache::lock($this->lockKey($request, $link), 5);

        try {
            return $lock->block(5, function () use ($request, $link, $accessCode): ?bool {
                $limiterKey = $this->limiterKey($request, $link);

                if (RateLimiter::tooManyAttempts($limiterKey, 5)) {
                    return null;
                }

                if (! Hash::check($accessCode, (string) $link->access_code)) {
                    RateLimiter::hit($limiterKey, 300);

                    return false;
                }

                RateLimiter::clear($limiterKey);
                $request->session()->put($this->sessionKey($link), [
                    'version' => $link->access_code_version,
                    'expires_at' => now()->addSeconds(self::LIFETIME_SECONDS)->getTimestamp(),
                ]);

                return true;
            });
        } catch (LockTimeoutException) {
            return null;
        }
    }

    private function sessionKey(Link $link): string
    {
        return "unlocked-links.{$link->id}";
    }

    private function lockKey(Request $request, Link $link): string
    {
        return 'link-access-lock:'.$this->limiterKey($request, $link);
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
