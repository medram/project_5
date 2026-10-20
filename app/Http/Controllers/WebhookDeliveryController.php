<?php

namespace App\Http\Controllers;

use App\Models\Webhook;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class WebhookDeliveryController extends Controller
{
    public function index(Webhook $webhook): JsonResponse
    {
        Gate::authorize('view', $webhook);

        return response()->json([
            'deliveries' => $webhook->deliveries()
                ->latest()
                ->limit(50)
                ->get()
                ->map(fn ($delivery): array => [
                    'id' => $delivery->id,
                    'eventUuid' => $delivery->event_uuid,
                    'eventName' => $delivery->event_name,
                    'attemptCount' => $delivery->attempt_count,
                    'state' => $delivery->state,
                    'responseCode' => $delivery->response_code,
                    'failureDetail' => $delivery->failure_detail,
                    'completedAt' => $delivery->completed_at?->toISOString(),
                ])
                ->values()
                ->all(),
        ]);
    }
}
