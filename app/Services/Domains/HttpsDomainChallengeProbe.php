<?php

namespace App\Services\Domains;

use Illuminate\Support\Facades\Http;

class HttpsDomainChallengeProbe
{
    public function respondsWithToken(string $hostname, string $token): bool
    {
        $addresses = $this->resolvePublicAddresses($hostname);

        if ($addresses === [] || ! defined('CURLOPT_RESOLVE')) {
            return false;
        }

        $response = Http::withOptions([
            'curl' => [
                CURLOPT_RESOLVE => [$this->curlResolveEntry($hostname, $addresses)],
            ],
        ])
            ->accept('text/plain')
            ->connectTimeout(5)
            ->timeout(10)
            ->withoutRedirecting()
            ->get("https://{$hostname}/.well-known/linkpilot-domain/{$token}");

        return $response->status() === 200
            && hash_equals($token, trim($response->body()));
    }

    /**
     * @return list<string>
     */
    protected function resolvePublicAddresses(string $hostname): array
    {
        $records = dns_get_record($hostname, DNS_A | DNS_AAAA);

        if ($records === false || $records === []) {
            return [];
        }

        $addresses = [];

        foreach ($records as $record) {
            $address = $record['ip'] ?? $record['ipv6'] ?? null;

            if (
                ! is_string($address)
                || filter_var(
                    $address,
                    FILTER_VALIDATE_IP,
                    FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
                ) === false
            ) {
                return [];
            }

            $addresses[] = $address;
        }

        return array_values(array_unique($addresses));
    }

    /**
     * @param  list<string>  $addresses
     */
    private function curlResolveEntry(string $hostname, array $addresses): string
    {
        $pinnedAddresses = array_map(
            fn (string $address): string => str_contains($address, ':')
                ? "[{$address}]"
                : $address,
            $addresses,
        );

        return "{$hostname}:443:".implode(',', $pinnedAddresses);
    }
}
