<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceApiToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWorkspaceApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();

        abort_unless($user instanceof User && $token instanceof WorkspaceApiToken, 401);

        $workspace = Workspace::query()->find($token->workspace_id);
        abort_unless($workspace instanceof Workspace && $user->belongsToWorkspace($workspace), 403);

        $request->attributes->set('api_workspace', $workspace);

        return $next($request);
    }
}
