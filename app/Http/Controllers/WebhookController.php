<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWebhookRequest;
use App\Models\User;
use App\Models\Webhook;
use App\Services\Billing\EnforcesPlanLimits;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class WebhookController extends Controller
{
    public function __construct(private readonly EnforcesPlanLimits $planLimits) {}

    public function index(Request $request): Response
    {
        return Inertia::render('webhooks/index', [
            'webhooks' => $request->user()->webhooks()
                ->withCount('deliveries')
                ->latest()
                ->get()
                ->map(fn (Webhook $webhook): array => $this->data($webhook))
                ->values()
                ->all(),
            'supportedEvents' => Webhook::supportedEvents(),
            'oneTimeSecret' => session('webhookSecret'),
        ]);
    }

    public function store(StoreWebhookRequest $request): RedirectResponse
    {
        $attributes = $request->validated();
        $secret = Webhook::generateSecret();

        $this->planLimits->create(
            $request->user(),
            'webhooks',
            function (User $user) use ($attributes, $secret): Webhook {
                $webhook = new Webhook($attributes);
                $webhook->user_id = $user->id;
                $webhook->secret = $secret;
                $webhook->save();

                return $webhook;
            },
        );

        return to_route('webhooks.index')->with('webhookSecret', $secret);
    }

    public function update(StoreWebhookRequest $request, Webhook $webhook): RedirectResponse
    {
        Gate::authorize('update', $webhook);

        $webhook->update([
            ...$request->validated(),
            'revision' => $webhook->revision + 1,
        ]);
        $this->cancelPendingDeliveries($webhook);

        return to_route('webhooks.index');
    }

    public function rotate(Webhook $webhook): RedirectResponse
    {
        Gate::authorize('update', $webhook);

        $secret = $webhook->rotateSecret();
        $this->cancelPendingDeliveries($webhook);

        return to_route('webhooks.index')->with('webhookSecret', $secret);
    }

    public function destroy(Webhook $webhook): RedirectResponse
    {
        Gate::authorize('delete', $webhook);

        $this->cancelPendingDeliveries($webhook);
        $webhook->delete();

        return to_route('webhooks.index');
    }

    private function cancelPendingDeliveries(Webhook $webhook): void
    {
        $webhook->deliveries()
            ->where('state', 'pending')
            ->update([
                'state' => 'cancelled',
                'completed_at' => now(),
            ]);
    }

    /**
     * @return array{id: int, name: string, url: string, events: array<int, string>, isActive: bool, revision: int, secretRotatedAt: string|null, deliveriesCount: int}
     */
    private function data(Webhook $webhook): array
    {
        return [
            'id' => $webhook->id,
            'name' => $webhook->name,
            'url' => $webhook->url,
            'events' => $webhook->events,
            'isActive' => $webhook->is_active,
            'revision' => $webhook->revision,
            'secretRotatedAt' => $webhook->secret_rotated_at?->toISOString(),
            'deliveriesCount' => $webhook->deliveries_count ?? 0,
        ];
    }
}
