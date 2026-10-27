<?php

namespace App\Services\Links;

use App\Models\Link;
use Illuminate\Http\Request;

class LinkRoutingResolver
{
    public function __construct(private readonly TrustedCountryResolver $countries) {}

    public function target(Request $request, Link $link): ?string
    {
        $matches = [
            'country' => $this->countries->resolve($request),
            'device' => $this->device($request->userAgent() ?? ''),
            'platform' => $this->platform($request->userAgent() ?? ''),
        ];

        foreach ($link->routingRules as $rule) {
            if (($matches[$rule->rule_type] ?? null) === $rule->match_value) {
                return $rule->target_url;
            }
        }

        return null;
    }

    private function device(string $userAgent): ?string
    {
        $agent = strtolower($userAgent);

        if ($agent === '') {
            return null;
        }

        if (preg_match('/ipad|tablet|kindle|silk/', $agent) === 1 || (str_contains($agent, 'android') && ! str_contains($agent, 'mobile'))) {
            return 'tablet';
        }

        if (preg_match('/mobile|iphone|ipod|windows phone/', $agent) === 1) {
            return 'mobile';
        }

        return 'desktop';
    }

    private function platform(string $userAgent): ?string
    {
        $agent = strtolower($userAgent);

        return match (true) {
            $agent === '' => null,
            str_contains($agent, 'android') => 'android',
            preg_match('/iphone|ipad|ipod/', $agent) === 1 => 'ios',
            str_contains($agent, 'cros') => 'chromeos',
            str_contains($agent, 'windows') => 'windows',
            preg_match('/macintosh|mac os x/', $agent) === 1 => 'macos',
            str_contains($agent, 'linux') => 'linux',
            default => null,
        };
    }
}
