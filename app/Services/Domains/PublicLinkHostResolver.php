<?php

namespace App\Services\Domains;

use App\Models\CustomDomain;
use App\Models\Link;
use Illuminate\Http\Request;

class PublicLinkHostResolver
{
    public function __construct(private readonly HostNormalizer $hosts) {}

    public function resolveLink(Request $request, string $slug): Link
    {
        $host = $this->requestHost($request);

        abort_unless($host !== null, 404);

        if ($this->hosts->isDefaultHost($host['hostname'])) {
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
            || $this->hosts->isDefaultHost($host['hostname'])
        ) {
            return null;
        }

        return $host['hostname'];
    }

    public function isDefaultHost(string $hostname): bool
    {
        return $this->hosts->isDefaultHost($hostname);
    }

    /**
     * @return array{hostname: string, port: int|null}|null
     */
    private function requestHost(Request $request): ?array
    {
        return $this->hosts->parseAuthority((string) $request->headers->get('Host', ''));
    }
}
