<?php

namespace App\Services\Links;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;

class TrustedCountryResolver
{
    public function resolve(Request $request): ?string
    {
        $header = config('linkpilot.country_targeting.header');
        $trustedCidrs = config('linkpilot.country_targeting.trusted_proxy_cidrs', []);
        $peer = $request->server->get('REMOTE_ADDR');

        if (! is_string($header) || $header === '' || ! is_array($trustedCidrs) || $trustedCidrs === []) {
            return null;
        }

        if (! is_string($peer) || ! IpUtils::checkIp($peer, $trustedCidrs)) {
            return null;
        }

        $country = strtoupper(trim((string) $request->header($header)));

        if (preg_match('/\A[A-Z]{2}\z/', $country) !== 1 || in_array($country, ['XX', 'T1'], true)) {
            return null;
        }

        return $country;
    }
}
