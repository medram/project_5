<?php

namespace App\Services\QrCodes;

use App\Models\QrCode;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class QrAccessUnlocks
{
    private const LIFETIME_SECONDS = 28_800;

    public function allows(Request $request, QrCode $qrCode): bool
    {
        $key = $this->sessionKey($qrCode);
        $unlock = $request->session()->get($key);

        if (! is_array($unlock)) {
            return false;
        }

        $valid = ($unlock['version'] ?? null) === $qrCode->access_code_version
            && is_int($unlock['expires_at'] ?? null)
            && $unlock['expires_at'] > now()->getTimestamp();

        if (! $valid) {
            $request->session()->forget($key);
        }

        return $valid;
    }

    public function attempt(Request $request, QrCode $qrCode, string $accessCode): ?bool
    {
        $lock = Cache::lock('qr-access-lock:'.$this->limiterKey($request, $qrCode), 5);

        try {
            return $lock->block(5, function () use ($request, $qrCode, $accessCode): ?bool {
                $key = $this->limiterKey($request, $qrCode);
                if (RateLimiter::tooManyAttempts($key, 5)) {
                    return null;
                }

                if (! Hash::check($accessCode, (string) $qrCode->access_code)) {
                    RateLimiter::hit($key, 300);

                    return false;
                }

                RateLimiter::clear($key);
                $request->session()->put($this->sessionKey($qrCode), [
                    'version' => $qrCode->access_code_version,
                    'expires_at' => now()->addSeconds(self::LIFETIME_SECONDS)->getTimestamp(),
                ]);

                return true;
            });
        } catch (LockTimeoutException) {
            return null;
        }
    }

    private function sessionKey(QrCode $qrCode): string
    {
        return "unlocked-qr-codes.{$qrCode->id}";
    }

    private function limiterKey(Request $request, QrCode $qrCode): string
    {
        $ip = trim((string) $request->ip());
        $normalizedIp = filter_var($ip, FILTER_VALIDATE_IP) === false
            ? strtolower($ip)
            : (string) inet_ntop((string) inet_pton($ip));
        $visitor = hash_hmac('sha256', $normalizedIp, (string) config('app.key'));

        return "qr-access:{$qrCode->id}:{$visitor}";
    }
}
