<?php

namespace App\Services\Security;

use App\Services\Settings\PlatformSettings;
use Illuminate\Support\Facades\Http;

class TurnstileVerifier
{
    public function __construct(private readonly PlatformSettings $settings) {}

    public function enabled(): bool
    {
        $captcha = $this->settings->resolved()['captcha'] ?? [];

        return ($captcha['provider'] ?? 'none') === 'turnstile'
            && (bool) ($captcha['on_registration'] ?? false)
            && is_string($captcha['site_key'] ?? null)
            && $captcha['site_key'] !== ''
            && $this->settings->secret('captcha', 'secret') !== null;
    }

    public function required(): bool
    {
        $captcha = $this->settings->resolved()['captcha'] ?? [];

        return ($captcha['provider'] ?? 'none') === 'turnstile'
            && (bool) ($captcha['on_registration'] ?? false);
    }

    public function verify(?string $token, ?string $ip): bool
    {
        if (! $this->required()) {
            return true;
        }

        if (! $this->enabled()) {
            return false;
        }

        if (! is_string($token) || $token === '') {
            return false;
        }

        $captcha = $this->settings->resolved()['captcha'];
        $expectedHost = parse_url((string) config('app.url'), PHP_URL_HOST);
        $response = Http::timeout(5)->asForm()->post(
            (string) config('services.turnstile.verify_url', 'https://challenges.cloudflare.com/turnstile/v0/siteverify'),
            [
                'secret' => $this->settings->secret('captcha', 'secret'),
                'response' => $token,
                'remoteip' => $ip,
            ],
        );

        if (! $response->successful()) {
            return false;
        }

        $data = $response->json();

        return is_array($data)
            && ($data['success'] ?? false) === true
            && (! is_string($expectedHost) || ($data['hostname'] ?? null) === $expectedHost)
            && ($data['action'] ?? null) === 'register';
    }
}
