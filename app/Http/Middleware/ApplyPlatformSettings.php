<?php

namespace App\Http\Middleware;

use App\Services\Settings\PlatformSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApplyPlatformSettings
{
    public function __construct(private readonly PlatformSettings $settings) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $this->settings->applyRuntime();

        return $next($request);
    }
}