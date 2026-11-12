<?php

namespace App\Services\Accounts;

final class DeviceLabel
{
    public function fromUserAgent(?string $userAgent): string
    {
        $agent = mb_strtolower(trim((string) $userAgent));

        if ($agent === '') {
            return 'Unknown device';
        }

        $platform = match (true) {
            str_contains($agent, 'iphone'), str_contains($agent, 'ipad'), str_contains($agent, 'ios') => 'iOS',
            str_contains($agent, 'android') => 'Android',
            str_contains($agent, 'windows') => 'Windows',
            str_contains($agent, 'macintosh'), str_contains($agent, 'mac os') => 'macOS',
            str_contains($agent, 'linux') => 'Linux',
            default => 'Unknown platform',
        };

        $browser = match (true) {
            str_contains($agent, 'edg/'), str_contains($agent, 'edge/') => 'Edge',
            str_contains($agent, 'fxios'), str_contains($agent, 'firefox') => 'Firefox',
            str_contains($agent, 'crios'), str_contains($agent, 'chrome') => 'Chrome',
            str_contains($agent, 'safari') => 'Safari',
            default => 'Unknown browser',
        };

        if ($platform === 'Unknown platform' && $browser === 'Unknown browser') {
            return 'Unknown device';
        }

        return "{$browser} on {$platform}";
    }
}
