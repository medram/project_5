<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PlanMetric;
use App\Http\Requests\Api\V1\WebhookRequest as ApiWebhookRequest;
use App\Http\Resources\Api\V1\WebhookResource;
use App\Models\User;
use App\Models\Webhook;
use App\Models\Workspace;
use App\Services\Billing\EnforcesPlanLimits;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class WebhookController extends ApiController
{
    public function __construct(private readonly EnforcesPlanLimits $planLimits) {}

    public function index(Request $request): JsonResponse
    {
        $page = $this->workspace($request)->webhooks()
            ->getQuery()
            ->orderBy('id')
            ->cursorPaginate($this->perPage($request));

        return $this->cursorResponse($request, $page, WebhookResource::class);
    }

    public function store(ApiWebhookRequest $request): JsonResponse
    {
        $workspace = $this->workspace($request);
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        Gate::authorize('manageIntegrations', $workspace);
        $attributes = $request->validated();
        $secret = Webhook::generateSecret();

        $webhook = $this->planLimits->create(
            $workspace,
            PlanMetric::Webhooks,
            function (Workspace $lockedWorkspace) use ($actor, $attributes, $secret): Webhook {
                $webhook = $lockedWorkspace->webhooks()->make($attributes);
                $webhook->forceFill([
                    'user_id' => $actor->getKey(),
                    'secret' => $secret,
                    'revision' => 1,
                ])->save();

                return $webhook;
            },
        );

        return (new WebhookResource($webhook))
            ->additional(['secret' => $secret])
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, string $webhook): WebhookResource
    {
        $webhook = $this->workspace($request)->webhooks()->findOrFail($webhook);
        Gate::authorize('view', $webhook);

        return new WebhookResource($webhook);
    }

    public function update(ApiWebhookRequest $request, string $webhook): WebhookResource
    {
        $webhook = $this->workspace($request)->webhooks()->findOrFail($webhook);
        Gate::authorize('update', $webhook);
        $webhook->update([
            ...$request->validated(),
            'revision' => $webhook->revision + 1,
        ]);
        $webhook->cancelPendingDeliveries();

        return new WebhookResource($webhook->fresh());
    }

    public function destroy(Request $request, string $webhook): JsonResponse
    {
        $webhook = $this->workspace($request)->webhooks()->findOrFail($webhook);
        Gate::authorize('delete', $webhook);
        $webhook->cancelPendingDeliveries();
        $webhook->delete();

        return response()->json(status: 204);
    }
}
