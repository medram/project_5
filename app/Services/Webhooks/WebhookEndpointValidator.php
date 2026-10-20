<?php

namespace App\Services\Webhooks;

class WebhookEndpointValidator
{
    /**
     * Address space LinkPilot must never open a connection to. Includes the
     * private, loopback, link-local, carrier-grade NAT, documentation,
     * benchmarking, multicast, and reserved ranges for both families, plus the
     * tunnelling ranges that can embed an IPv4 address.
     */
    private const BLOCKED_PREFIXES = [
        '0.0.0.0/8',
        '10.0.0.0/8',
        '100.64.0.0/10',
        '127.0.0.0/8',
        '169.254.0.0/16',
        '172.16.0.0/12',
        '192.0.0.0/24',
        '192.0.2.0/24',
        '192.88.99.0/24',
        '192.168.0.0/16',
        '198.18.0.0/15',
        '198.51.100.0/24',
        '203.0.113.0/24',
        '224.0.0.0/4',
        '240.0.0.0/4',
        '::/128',
        '::1/128',
        '::ffff:0:0/96',
        '64:ff9b::/96',
        '64:ff9b:1::/48',
        '100::/64',
        '2001::/32',
        '2001:db8::/32',
        '2002::/16',
        'fc00::/7',
        'fe80::/10',
        'ff00::/8',
    ];

    /**
     * Validate a webhook URL and return a CURLOPT_RESOLVE entry that pins the
     * connection to freshly-resolved public addresses, or null when unsafe.
     */
    public function validate(string $url): ?string
    {
        $parts = parse_url($url);

        if (! is_array($parts) || ($parts['scheme'] ?? null) !== 'https') {
            return null;
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        $host = $parts['host'] ?? null;

        if (! is_string($host) || $host === '') {
            return null;
        }

        $host = trim($host, '[]');

        // Reject literal addresses outright; only validated hostnames are
        // reachable, which keeps DNS-rebinding and direct-IP SSRF off the table.
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return null;
        }

        $addresses = $this->resolveAddresses($host);

        if ($addresses === []) {
            return null;
        }

        foreach ($addresses as $address) {
            if (! $this->isPublicAddress($address)) {
                return null;
            }
        }

        $port = $parts['port'] ?? 443;

        $pinned = array_map(
            fn (string $address): string => str_contains($address, ':') ? "[{$address}]" : $address,
            $addresses,
        );

        return "{$host}:{$port}:".implode(',', $pinned);
    }

    /**
     * @return list<string>
     */
    protected function resolveAddresses(string $host): array
    {
        $records = dns_get_record($host, DNS_A | DNS_AAAA);

        if ($records === false || $records === []) {
            return [];
        }

        $addresses = [];

        foreach ($records as $record) {
            $address = $record['ip'] ?? $record['ipv6'] ?? null;

            if (is_string($address) && $address !== '') {
                $addresses[] = $address;
            }
        }

        return array_values(array_unique($addresses));
    }

    private function isPublicAddress(string $address): bool
    {
        $packed = @inet_pton($address);

        if ($packed === false) {
            return false;
        }

        foreach (self::BLOCKED_PREFIXES as $prefix) {
            [$network, $bits] = explode('/', $prefix);

            if ($this->matchesPrefix($packed, (string) inet_pton($network), (int) $bits)) {
                return false;
            }
        }

        return true;
    }

    private function matchesPrefix(string $packed, string $network, int $bits): bool
    {
        if (strlen($network) !== strlen($packed)) {
            return false;
        }

        $bytes = intdiv($bits, 8);
        $remainder = $bits % 8;

        if ($bytes > 0 && substr($packed, 0, $bytes) !== substr($network, 0, $bytes)) {
            return false;
        }

        if ($remainder === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $remainder)) & 0xFF;

        return (ord($packed[$bytes]) & $mask) === (ord($network[$bytes]) & $mask);
    }
}
