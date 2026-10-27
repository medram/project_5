<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\LinkRequest as ApiLinkRequest;
use App\Http\Resources\Api\V1\LinkResource;
use App\Models\Link;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Billing\EnforcesPlanLimits;
use App\Services\Links\LinkRevisionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class LinkController extends ApiController
{
    public function __construct(
        private readonly EnforcesPlanLimits $planLimits,
        private readonly LinkRevisionService $revisions,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $page = $this->workspace($request)->links()
            ->getQuery()
            ->orderBy('id')
            ->cursorPaginate($this->perPage($request));

        return $this->cursorResponse($request, $page, LinkResource::class);
    }

    public function store(ApiLinkRequest $request): JsonResponse
    {
        $workspace = $this->workspace($request);
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        $attributes = $request->validated();

        $link = $this->planLimits->create(
            $workspace,
            'links',
            function (Workspace $lockedWorkspace) use ($actor, $attributes): Link {
                $link = $lockedWorkspace->links()->create([
                    'user_id' => $actor->getKey(),
                    'destination_url' => $attributes['destination_url'],
                    'title' => $attributes['title'] ?? null,
                    'slug' => $attributes['slug'] ?? null,
                    'is_active' => (bool) ($attributes['is_active'] ?? true),
                    'delivery_mode' => 'direct',
                    'approval_required' => false,
                ]);
                $this->revisions->recordInitial($link, [], [], $actor);

                return $link;
            },
        );

        return (new LinkResource($link))->response()->setStatusCode(201);
    }

    public function show(Request $request, string $link): LinkResource
    {
        $link = $this->workspace($request)->links()->findOrFail($link);
        Gate::authorize('view', $link);

        return new LinkResource($link);
    }

    public function update(ApiLinkRequest $request, string $link): LinkResource
    {
        $workspace = $this->workspace($request);
        $link = $workspace->links()->findOrFail($link);
        Gate::authorize('update', $link);
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        $attributes = $request->validated();

        DB::transaction(function () use ($workspace, $link, $attributes, $actor): void {
            $lockedLink = $workspace->links()->lockForUpdate()->findOrFail($link->getKey());
            $this->revisions->update($lockedLink, $attributes, null, null, $actor);
        });

        return new LinkResource($link->fresh());
    }

    public function destroy(Request $request, string $link): JsonResponse
    {
        $link = $this->workspace($request)->links()->findOrFail($link);
        Gate::authorize('delete', $link);
        $link->delete();

        return response()->json(status: 204);
    }
}
