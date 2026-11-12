<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PlanMetric;
use App\Http\Requests\Api\V1\LinkPageRequest as ApiLinkPageRequest;
use App\Http\Resources\Api\V1\LinkPageResource;
use App\Models\LinkPage;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Billing\EnforcesPlanLimits;
use App\Services\Content\ContentMediaStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class LinkPageController extends ApiController
{
    public function __construct(
        private readonly EnforcesPlanLimits $planLimits,
        private readonly ContentMediaStorage $media,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $page = $this->workspace($request)->linkPages()
            ->getQuery()
            ->orderBy('id')
            ->cursorPaginate($this->perPage($request));

        return $this->cursorResponse($request, $page, LinkPageResource::class);
    }

    public function store(ApiLinkPageRequest $request): JsonResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        $attributes = $request->validated();

        $linkPage = $this->planLimits->create(
            $this->workspace($request),
            PlanMetric::LinkPages,
            function (Workspace $workspace) use ($attributes, $actor): LinkPage {
                $linkPage = $workspace->linkPages()->create([
                    ...$attributes,
                    'user_id' => $actor->getKey(),
                ]);
                $linkPage->variants()->create([
                    'name' => 'Control',
                    'weight' => 100,
                    'is_control' => true,
                    'is_active' => true,
                ]);

                return $linkPage;
            },
        );

        return (new LinkPageResource($linkPage))->response()->setStatusCode(201);
    }

    public function show(Request $request, string $linkPage): LinkPageResource
    {
        $linkPage = $this->workspace($request)->linkPages()->findOrFail($linkPage);
        Gate::authorize('view', $linkPage);

        return new LinkPageResource($linkPage);
    }

    public function update(ApiLinkPageRequest $request, string $linkPage): LinkPageResource
    {
        $linkPage = $this->workspace($request)->linkPages()->findOrFail($linkPage);
        Gate::authorize('update', $linkPage);
        $linkPage->update($request->validated());

        return new LinkPageResource($linkPage->fresh());
    }

    public function destroy(Request $request, string $linkPage): JsonResponse
    {
        $linkPage = $this->workspace($request)->linkPages()->findOrFail($linkPage);
        Gate::authorize('delete', $linkPage);

        DB::transaction(function () use ($linkPage): void {
            foreach ($linkPage->blocks()->with('contentMedia')->get() as $block) {
                if ($block->contentMedia !== null) {
                    $this->media->delete($block->contentMedia);
                }
            }
            $linkPage->delete();
        });

        return response()->json(status: 204);
    }
}
