<?php

namespace App\Services\Analytics;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RequestDimensions
{
    public function __construct(
        private readonly GeoIpCountryResolver $geoIp,
    ) {}

    /**
     * Coarse, request-derived dimensions only. Raw IP addresses and user
     * agents are deliberately never persisted.
     *
     * @return array{
     *     referrer_host: ?string,
     *     browser: string,
     *     platform: string,
     *     country_code: ?string
     * }
     */
    public function forRequest(Request $request): array
    {
        return [
            'referrer_host' => $this->referrerHost($request),
            'browser' => $this->browser($request),
            'platform' => $this->platform($request),
            'country_code' => $this->geoIp->countryCode((string) $request->ip()),
        ];
    }

    /**
     * Bucket a known platform without storing a second derived column.
     */
    public static function device(?string $platform): ?string
    {
        return match ($platform) {
            'Android', 'iOS' => 'Mobile',
            'Windows', 'macOS', 'Linux' => 'Desktop',
            'Other' => 'Other',
            default => null,
        };
    }

    private function referrerHost(Request $request): ?string
    {
        $referrer = $request->headers->get('Referer');
        $host = is_string($referrer) ? parse_url($referrer, PHP_URL_HOST) : null;

        return is_string($host) ? Str::lower(Str::limit($host, 253, '')) : null;
    }

    private function browser(Request $request): string
    {
        $userAgent = (string) $request->userAgent();

        return match (true) {
            str_contains($userAgent, 'Edg/') => 'Edge',
            str_contains($userAgent, 'Firefox/') => 'Firefox',
            str_contains($userAgent, 'Chrome/') => 'Chrome',
            str_contains($userAgent, 'Safari/') => 'Safari',
            default => 'Other',
        };
    }

    private function platform(Request $request): string
    {
        $userAgent = (string) $request->userAgent();

        return match (true) {
            str_contains($userAgent, 'Windows') => 'Windows',
            str_contains($userAgent, 'Android') => 'Android',
            str_contains($userAgent, 'iPhone'), str_contains($userAgent, 'iPad') => 'iOS',
            str_contains($userAgent, 'Macintosh') => 'macOS',
            str_contains($userAgent, 'Linux') => 'Linux',
            default => 'Other',
        };
    }
}
