<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\CursorPaginator;

abstract class ApiController extends Controller
{
    protected function workspace(Request $request): Workspace
    {
        $workspace = $request->attributes->get('api_workspace');
        abort_unless($workspace instanceof Workspace, 401);

        return $workspace;
    }

    protected function perPage(Request $request): int
    {
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        return (int) ($validated['per_page'] ?? 50);
    }

    /** @param class-string<JsonResource> $resourceClass */
    protected function cursorResponse(Request $request, CursorPaginator $paginator, string $resourceClass): JsonResponse
    {
        return response()->json([
            'data' => array_map(
                fn ($model): array => (new $resourceClass($model))->resolve($request),
                $paginator->items(),
            ),
            'meta' => [
                'per_page' => $paginator->perPage(),
                'next_cursor' => $paginator->nextCursor()?->encode(),
                'previous_cursor' => $paginator->previousCursor()?->encode(),
            ],
        ]);
    }
}
