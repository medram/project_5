<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PlanMetric;
use App\Http\Requests\Api\V1\BioPageRequest as ApiBioPageRequest;
use App\Http\Resources\Api\V1\BioPageResource;
use App\Models\BioPage;
use App\Models\User;
use App\Services\Billing\EnforcesPlanLimits;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class BioPageController extends ApiController
{
    public function __construct(private readonly EnforcesPlanLimits $planLimits) {}

    public function index(Request $request): JsonResponse
    {
        $page = $this->workspace($request)->bioPages()
            ->getQuery()
            ->orderBy('id')
            ->cursorPaginate($this->perPage($request));

        return $this->cursorResponse($request, $page, BioPageResource::class);
    }

    public function store(ApiBioPageRequest $request): JsonResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        $workspace = $this->workspace($request);
        $bioPage = $this->planLimits->create(
            $workspace,
            PlanMetric::BioPages,
            fn (): BioPage => $workspace->bioPages()->create([
                ...$request->validated(),
                'user_id' => $actor->getKey(),
            ]),
        );

        return (new BioPageResource($bioPage))->response()->setStatusCode(201);
    }

    public function show(Request $request, string $bioPage): BioPageResource
    {
        $bioPage = $this->workspace($request)->bioPages()->findOrFail($bioPage);
        Gate::authorize('view', $bioPage);

        return new BioPageResource($bioPage);
    }

    public function update(ApiBioPageRequest $request, string $bioPage): BioPageResource
    {
        $bioPage = $this->workspace($request)->bioPages()->findOrFail($bioPage);
        Gate::authorize('update', $bioPage);
        $bioPage->update($request->validated());

        return new BioPageResource($bioPage->fresh());
    }

    public function destroy(Request $request, string $bioPage): JsonResponse
    {
        $bioPage = $this->workspace($request)->bioPages()->findOrFail($bioPage);
        Gate::authorize('delete', $bioPage);
        $bioPage->delete();

        return response()->json(status: 204);
    }
}
