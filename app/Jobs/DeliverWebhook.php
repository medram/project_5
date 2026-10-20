<?php

namespace App\Jobs;

use App\Models\WebhookDelivery;
use App\Services\Webhooks\WebhookEndpointValidator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Throwable;

class DeliverWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $deliveryId) {}

    public function handle(WebhookEndpointValidator $validator): void
    {
        $delivery = WebhookDelivery::query()->find($this->deliveryId);

        if ($delivery === null || $delivery->state !== 'pending') {
            return;
        }

        $webhook = $delivery->webhook;

        if ($webhook === null || ! $webhook->is_active || $webhook->revision !== $delivery->revision) {
            $delivery->update(['state' => 'cancelled', 'completed_at' => now()]);

            return;
        }

        $attempt = $delivery->attempt_count + 1;
        $resolve = $validator->validate($webhook->url);

        if ($resolve === null) {
            $this->recordAttempt($delivery, $attempt, null, null, 'Endpoint validation failed');

            return;
        }

        try {
            $response = Http::withOptions(['curl' => [CURLOPT_RESOLVE => [$resolve]]])
                ->withHeaders([
                    'X-LinkPilot-Signature' => $webhook->sign($delivery->body),
                    'X-LinkPilot-Event' => $delivery->event_name,
                    'User-Agent' => 'LinkPilot-Webhooks/1.0',
                ])
                ->withBody($delivery->body, 'application/json')
                ->connectTimeout((int) config('services.linkpilot.webhooks.connect_timeout', 3))
                ->timeout((int) config('services.linkpilot.webhooks.timeout', 10))
                ->withoutRedirecting()
                ->post($webhook->url);
        } catch (Throwable $exception) {
            $this->recordAttempt($delivery, $attempt, null, null, 'Request failed');

            return;
        }

        if ($response->successful()) {
            $delivery->update([
                'attempt_count' => $attempt,
                'state' => 'delivered',
                'response_code' => $response->status(),
                'completed_at' => now(),
            ]);

            return;
        }

        $this->recordAttempt(
            $delivery,
            $attempt,
            $response->status(),
            mb_substr($response->body(), 0, (int) config('services.linkpilot.webhooks.excerpt_bytes', 500)),
            'Non-2xx response',
        );
    }

    private function recordAttempt(
        WebhookDelivery $delivery,
        int $attempt,
        ?int $code,
        ?string $excerpt,
        ?string $detail,
    ): void {
        $delivery->attempt_count = $attempt;
        $delivery->response_code = $code;
        $delivery->response_excerpt = $excerpt;
        $delivery->failure_detail = $detail;

        if ($attempt >= (int) config('services.linkpilot.webhooks.max_attempts', 3)) {
            $delivery->state = 'failed';
            $delivery->completed_at = now();
            $delivery->save();

            return;
        }

        $delivery->save();

        $delays = (array) config('services.linkpilot.webhooks.retry_delays', [60, 300]);
        $delay = $delays[$attempt - 1] ?? (end($delays) ?: 60);

        self::dispatch($delivery->id)->delay((int) $delay);
    }
}
