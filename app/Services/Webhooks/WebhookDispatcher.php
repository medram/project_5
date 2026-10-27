<?php

namespace App\Services\Webhooks;

use App\Jobs\DeliverWebhook;
use App\Models\AnalyticsEvent;
use App\Models\Webhook;
use App\Models\WebhookDelivery;

class WebhookDispatcher
{
    /**
     * Create a delivery and queue a job for every active webhook subscribed to
     * the persisted event. Called only after an event is durably recorded.
     */
    public function dispatch(AnalyticsEvent $event): void
    {
        $body = WebhookPayload::fromEvent($event);

        Webhook::query()
            ->where('workspace_id', $event->workspace_id)
            ->where('is_active', true)
            ->get()
            ->filter(fn (Webhook $webhook): bool => in_array(
                $event->event_name,
                (array) $webhook->events,
                true,
            ))
            ->each(function (Webhook $webhook) use ($body, $event): void {
                $delivery = WebhookDelivery::query()->create([
                    'webhook_id' => $webhook->id,
                    'revision' => $webhook->revision,
                    'event_uuid' => $event->uuid,
                    'event_name' => $event->event_name,
                    'body' => $body,
                    'attempt_count' => 0,
                    'state' => 'pending',
                ]);

                DeliverWebhook::dispatch($delivery->id);
            });
    }
}
