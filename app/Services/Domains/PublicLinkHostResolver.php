<?php

namespace App\Services\Domains;

use App\Models\CustomDomain;
use App\Models\Link;
use Illuminate\Http\Request;

class PublicLinkHostResolver
{
    public function resolveLink(Request $request, string $slug): Link
    {
        $host = $this->requestHost($request);

        abort_unless($host !== null, 404);

        if ($this->isDefaultHost($host['hostname'])) {
            return Link::query()
                ->where('slug', $slug)
                ->whereNull('custom_domain_id')
                ->firstOrFail();
        }

        abort_if($host['port'] !== null, 404);

        $domain = CustomDomain::query()
            ->where('hostname', $host['hostname'])
            ->where('status', 'active')
            ->firstOrFail();

        return Link::query()
            ->where('slug', $slug)
            ->where('custom_domain_id', $domain->id)
            ->firstOrFail();
    }

    public function canonicalUrl(Link $link): string
    {
        $link->loadMissing('customDomain');

        if ($link->customDomain !== null) {
            return "https://{$link->customDomain->hostname}/{$link->slug}";
        }

        return rtrim((string) config('app.url'), '/')."/{$link->slug}";
    }

    public function customHostnameFromRequest(Request $request): ?string
    {
        $host = $this->requestHost($request);

        if (
            $host === null
            || $host['port'] !== null
            || $this->isDefaultHost($host['hostname'])
        ) {
            return null;
        }

        return $host['hostname'];
    }

    /**
     * @return array{hostname: string, port: int|null}|null
     */
    private function requestHost(Request $request): ?array
    {
        $hostHeader = trim((string) $request->headers->get('Host', ''));

        if (
            preg_match(
                '/\A(?<hostname>[a-zA-Z0-9.-]+)(?::(?<port>[0-9]{1,5}))?\z/',
                $hostHeader,
                $matches,
            ) !== 1
        ) {
            return null;
        }

        $hostname = CustomDomain::normalizeHostname($matches['hostname']);
        $port = isset($matches['port']) && $matches['port'] !== ''
            ? (int) $matches['port']
            : null;

        if ($hostname === null || ($port !== null && ($port < 1 || $port > 65535))) {
            return null;
        }

        return [
            'hostname' => $hostname,
            'port' => $port,
        ];
    }

    public function isDefaultHost(string $hostname): bool
    {
        return in_array($hostname, $this->defaultHosts(), true);
    }

    /**
     * @return list<string>
     */
    private function defaultHosts(): array
    {
        $hosts = [];
        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);

        if (is_string($appHost)) {
            $normalized = CustomDomain::normalizeHostname($appHost);

            if ($normalized !== null) {
                $hosts[] = $normalized;
            }
        }

        foreach ((array) config('services.linkpilot.default_hosts', []) as $configuredHost) {
            if (! is_string($configuredHost)) {
                continue;
            }

            $parts = parse_url(
                str_contains($configuredHost, '://')
                    ? $configuredHost
                    : "https://{$configuredHost}",
            );
            $hostname = is_array($parts) && isset($parts['host']) && is_string($parts['host'])
                ? CustomDomain::normalizeHostname($parts['host'])
                : null;

            if ($hostname !== null) {
                $hosts[] = $hostname;
            }
        }

        return array_values(array_unique($hosts));
    }
}
