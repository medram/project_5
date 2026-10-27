<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveAccount
{
    /**
     * Determine if the authenticatable identity is a suspended account that
     * must be refused. Shared by the Fortify authentication paths so every
     * login surface rejects suspended accounts with the same decision.
     */
    public static function isSuspended(?Authenticatable $user): bool
    {
        return (bool) ($user?->is_suspended ?? false);
    }

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_if(static::isSuspended($request->user()), 403);

        return $next($request);
    }
}
