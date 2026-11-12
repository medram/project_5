<?php

namespace App\Http\Middleware;

use App\Services\Platform\PlatformAuthorization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdministrator
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user !== null && app(PlatformAuthorization::class)->canAccessAdmin($user), 403);

        return $next($request);
    }
}
