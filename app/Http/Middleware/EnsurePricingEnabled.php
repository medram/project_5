<?php

namespace App\Http\Middleware;

use App\Services\Settings\PlatformSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePricingEnabled
{
    public function __construct(private readonly PlatformSettings $settings) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $resolved = $this->settings->resolved();
        abort_unless((bool) ($resolved['landing']['pricing_enabled'] ?? true), 404);

        return $next($request);
    }
}
