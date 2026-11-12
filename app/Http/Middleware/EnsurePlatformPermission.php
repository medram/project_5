<?php

namespace App\Http\Middleware;

use App\Enums\PlatformPermission;
use App\Services\Platform\PlatformAuthorization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformPermission
{
    public function __construct(private PlatformAuthorization $authorization) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();
        $ability = PlatformPermission::tryFrom($permission);

        abort_if($user === null || $ability === null, 403);
        abort_unless($this->authorization->allows($user, $ability), 403);

        return $next($request);
    }
}
