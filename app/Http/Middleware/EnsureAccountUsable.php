<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureAccountUsable
{
    /**
     * Routes a pending-deletion account may still use.
     *
     * @var list<string>
     */
    private const ALLOWED_ROUTES = [
        'account.restore',
        'account.restore.store',
        'logout',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || $user->deletion_requested_at === null) {
            return $next($request);
        }

        $route = $request->route()?->getName();

        if (in_array($route, self::ALLOWED_ROUTES, true)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            abort(403, 'This account is scheduled for deletion.');
        }

        return redirect()->route('account.restore');
    }
}
