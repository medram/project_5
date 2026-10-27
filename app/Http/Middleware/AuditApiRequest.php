<?php

namespace App\Http\Middleware;

use App\Models\ApiAuditLog;
use App\Models\Workspace;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuditApiRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $workspace = $request->attributes->get('api_workspace');

        if ($workspace instanceof Workspace) {
            ApiAuditLog::query()->create([
                'workspace_id' => $workspace->getKey(),
                'actor_user_id' => $request->user()?->getAuthIdentifier(),
                'route' => $request->route()?->getName() ?? $request->route()?->uri() ?? 'unmatched',
                'method' => $request->method(),
                'status_code' => $response->getStatusCode(),
            ]);
        }

        return $response;
    }
}
