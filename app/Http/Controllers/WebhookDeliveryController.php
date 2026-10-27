<?php

namespace App\Http\Controllers;

use App\Models\Webhook;
use App\Http\Requests\RedeliverWebhookRequest;
use App\Jobs\DeliverWebhook;
use App\Services\Workspaces\WorkspaceContext;
use App\Models\WebhookDelivery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class WebhookDeliveryController extends Controller
{
    public function __construct(private readonly WorkspaceContext $workspaceContext) {}

    public function index(Request $request, Webhook $webhook): JsonResponse
    {
        $workspace = $this->workspaceContext->currentFor($request->user());
        abort_unless((int) $webhook->workspace_id === (int) $workspace->getKey(), 404);
        Gate::authorize('view', $webhook);

        return response()->json([
            'deliveries' => $webhook->deliveries()
                ->latest()
                ->limit(50)
                ->get()
                ->map(fn ($delivery): array => [
                    'id' => $delivery->id,
                    'sourceDeliveryId' => $delivery->source_delivery_id,
                    'eventUuid' => $delivery->event_uuid,
                    'eventName' => $delivery->event_name,
                    'attemptCount' => $delivery->attempt_count,
                    'state' => $delivery->state,
                    'responseCode' => $delivery->response_code,
                    'failureDetail' => $delivery->failure_detail,
                    'responseExcerpt' => $delivery->response_excerpt,
                    'completedAt' => $delivery->completed_at?->toISOString(),
                ])
                ->values()
                ->all(),
        ]);
    }

    public function redeliver(
        RedeliverWebhookRequest $request,
        Webhook $webhook,
        WebhookDelivery $delivery,
    ): RedirectResponse {
        $request->validated();
        abort_unless(in_array($delivery->state, ['failed', 'cancelled'], true), 409);
        abort_unless($webhook->is_active, 409);

        $redelivery = $webhook->deliveries()->create([
            'source_delivery_id' => $delivery->getKey(),
            'revision' => $webhook->revision,
            'event_uuid' => $delivery->event_uuid,
            'event_name' => $delivery->event_name,
            'body' => $delivery->body,
            'attempt_count' => 0,
            'state' => 'pending',
        ]);
        DeliverWebhook::dispatch($redelivery->getKey());

        return to_route('webhooks.index')->with('status', 'Redelivery queued.');
    }

}
