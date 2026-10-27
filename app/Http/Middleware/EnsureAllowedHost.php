<?php

namespace App\Http\Middleware;

use App\Models\CustomDomain;
use App\Services\Domains\HostNormalizer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureAllowedHost
{
    private const CUSTOM_DOMAIN_ROUTES = [
        'links.redirect',
        'links.unlock',
        'links.continue',
        'custom-domains.challenge',
    ];

    /**
     * Routes that claimed custom hosts serve only once the domain is active.
     * Consent must be same-origin with the served page, but a claimed yet
     * unverified host must not be able to record a grant cookie.
     */
    private const ACTIVE_CUSTOM_DOMAIN_ROUTES = [
        'tracking-consent.store',
    ];

    public function __construct(private readonly HostNormalizer $hosts) {}

    public function handle(Request $request, Closure $next): Response
    {
        $authority = $this->hosts->parseAuthority((string) $request->headers->get('Host', ''));

        abort_unless($authority !== null, 404);

        if ($this->hosts->isDefaultHost($authority['hostname'])) {
            return $next($request);
        }

        abort_if($authority['port'] !== null, 404);

        $routeName = $request->route()?->getName();

        if (in_array($routeName, self::ACTIVE_CUSTOM_DOMAIN_ROUTES, true)) {
            abort_unless(
                CustomDomain::query()
                    ->where('hostname', $authority['hostname'])
                    ->where('status', 'active')
                    ->exists(),
                404,
            );

            return $next($request);
        }

        abort_unless(in_array($routeName, self::CUSTOM_DOMAIN_ROUTES, true), 404);
        abort_unless(
            CustomDomain::query()->where('hostname', $authority['hostname'])->exists(),
            404,
        );

        return $next($request);
    }
}
