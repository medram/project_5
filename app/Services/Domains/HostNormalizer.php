<?php

namespace App\Services\Domains;

final class HostNormalizer
{
    public function normalizeHostname(string $hostname): ?string
    {
        $hostname = rtrim(trim($hostname), '.');

        if ($hostname === '') {
            return null;
        }

        if (filter_var($hostname, FILTER_VALIDATE_IP) !== false) {
            return strtolower($hostname);
        }

        $info = [];
        $ascii = idn_to_ascii($hostname, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46, $info);

        if (
            $ascii === false
            || ($info['errors'] ?? 0) !== 0
            || strlen($ascii) > 253
        ) {
            return null;
        }

        $ascii = strtolower($ascii);
        $labels = explode('.', $ascii);

        foreach ($labels as $label) {
            if (
                $label === ''
                || strlen($label) > 63
                || preg_match('/\A[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\z/', $label) !== 1
            ) {
                return null;
            }
        }

        return $ascii;
    }

    /**
     * @return array{hostname: string, port: int|null}|null
     */
    public function parseAuthority(string $authority): ?array
    {

        if ($authority === '' || str_contains($authority, '@')) {
            return null;
        }

        if (str_starts_with($authority, '[')) {
            if (preg_match('/\A\[(?<hostname>[^]]+)](?::(?<port>[0-9]{1,5}))?\z/', $authority, $matches) !== 1) {
                return null;
            }
        } elseif (preg_match('/\A(?<hostname>[^:]+)(?::(?<port>[0-9]{1,5}))?\z/', $authority, $matches) !== 1) {
            return null;
        }

        $hostname = $this->normalizeHostname($matches['hostname']);
        $port = isset($matches['port']) && $matches['port'] !== '' ? (int) $matches['port'] : null;

        if ($hostname === null || ($port !== null && ($port < 1 || $port > 65535))) {
            return null;
        }

        return ['hostname' => $hostname, 'port' => $port];
    }

    public function isDefaultHost(string $hostname): bool
    {
        $normalized = $this->normalizeHostname($hostname);

        return $normalized !== null && in_array($normalized, $this->defaultHosts(), true);
    }

    /** @return list<string> */
    public function defaultHosts(): array
    {
        $authorities = [(string) parse_url((string) config('app.url'), PHP_URL_HOST)];

        foreach ((array) config('linkpilot.default_hosts', []) as $configuredHost) {
            if (! is_string($configuredHost)) {
                continue;
            }

            $parts = parse_url(str_contains($configuredHost, '://') ? $configuredHost : "https://{$configuredHost}");

            if (is_array($parts) && isset($parts['host']) && is_string($parts['host'])) {
                $authorities[] = $parts['host'];
            }
        }

        $hosts = [];

        foreach ($authorities as $authority) {
            $hostname = $this->normalizeHostname($authority);

            if ($hostname !== null) {
                $hosts[] = $hostname;
            }
        }

        return array_values(array_unique($hosts));
    }
}
