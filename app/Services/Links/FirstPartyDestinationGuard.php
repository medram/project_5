<?php

namespace App\Services\Links;

use App\Models\CustomDomain;
use App\Services\Domains\HostNormalizer;

final readonly class FirstPartyDestinationGuard
{
    public function __construct(private HostNormalizer $hostNormalizer) {}

    public function rejects(string $url): bool
    {
        $hostname = parse_url($url, PHP_URL_HOST);

        if (! is_string($hostname)) {
            return false;
        }

        $hostname = $this->hostNormalizer->normalizeHostname($hostname);

        if ($hostname === null) {
            return false;
        }

        return $this->hostNormalizer->isDefaultHost($hostname)
            || CustomDomain::query()->where('hostname', $hostname)->exists();
    }
}
