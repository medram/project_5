<?php

namespace App\Http\Controllers;

use App\Enums\PlanMetric;
use App\Http\Requests\StoreWebhookRequest;
use App\Models\Webhook;
use App\Models\Workspace;
use App\Services\Billing\EnforcesPlanLimits;
use App\Services\Workspaces\WorkspaceContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class WebhookController extends Controller
{
    public function __construct(
        private readonly EnforcesPlanLimits $planLimits,
        private readonly WorkspaceContext $workspaceContext,
    ) {}

    public function index(Request $request): Response
    {
        $workspace = $this->workspaceContext->currentFor($request->user());
        $canManageIntegrations = Gate::allows('manageIntegrations', $workspace);

        return Inertia::render('webhooks/index', [
            'webhooks' => $workspace->webhooks()
                ->withCount('deliveries')
                ->latest()
                ->get()
                ->map(fn (Webhook $webhook): array => $this->data($webhook, $canManageIntegrations))
                ->values()
                ->all(),
            'supportedEvents' => Webhook::supportedEvents(),
            'canManageIntegrations' => $canManageIntegrations,
            'oneTimeSecret' => $request->session()->pull('webhookSecret'),
        ]);
    }

    public function store(StoreWebhookRequest $request): RedirectResponse
    {
        $workspace = $this->workspaceContext->currentFor($request->user());
        Gate::authorize('manageIntegrations', $workspace);
        $attributes = $request->validated();
        $secret = Webhook::generateSecret();

        $this->planLimits->create(
            $workspace,
            PlanMetric::Webhooks,
            function (Workspace $workspace) use ($request, $attributes, $secret): Webhook {
                $webhook = $workspace->webhooks()->make($attributes);

                $webhook->forceFill([
                    'user_id' => $request->user()->id,
                    'secret' => $secret,
                ]);
                $webhook->save();

                return $webhook;
            },
        );

        return to_route('webhooks.index')->with('webhookSecret', $secret);
    }

    public function update(StoreWebhookRequest $request, Webhook $webhook): RedirectResponse
    {
        $this->authorizeCurrentWorkspace($request, $webhook);
        Gate::authorize('update', $webhook);

        $webhook->update([
            ...$request->validated(),
            'revision' => $webhook->revision + 1,
        ]);
        $webhook->cancelPendingDeliveries();

        return to_route('webhooks.index');
    }

    public function rotate(Request $request, Webhook $webhook): RedirectResponse
    {
        $this->authorizeCurrentWorkspace($request, $webhook);
        Gate::authorize('update', $webhook);

        $secret = $webhook->rotateSecret();
        $webhook->cancelPendingDeliveries();

        return to_route('webhooks.index')->with('webhookSecret', $secret);
    }

    public function destroy(Request $request, Webhook $webhook): RedirectResponse
    {
        $this->authorizeCurrentWorkspace($request, $webhook);
        Gate::authorize('delete', $webhook);

        $webhook->cancelPendingDeliveries();
        $webhook->delete();

        return to_route('webhooks.index');
    }

    private function authorizeCurrentWorkspace(Request $request, Webhook $webhook): Workspace
    {
        $workspace = $this->workspaceContext->currentFor($request->user());
        abort_unless((int) $webhook->workspace_id === (int) $workspace->getKey(), 404);

        return $workspace;
    }

    /**
     * @return array{
     *     id: int,
     *     name: string,
     *     url: string|null,
     *     events: array<int, string>,
     *     isActive: bool,
     *     revision: int,
     *     secretRotatedAt: string|null,
     *     deliveriesCount: int,
     *     health: array{
     *         counts: array{pending: int, delivered: int, failed: int, cancelled: int},
     *         latestState: string|null
     *     }|null
     * }
     */
    private function data(Webhook $webhook, bool $canManageIntegrations): array
    {
        return [
            'id' => $webhook->id,
            'name' => $webhook->name,
            'url' => $canManageIntegrations ? $webhook->url : null,
            'events' => $webhook->events,
            'isActive' => $webhook->is_active,
            'revision' => $webhook->revision,
            'secretRotatedAt' => $webhook->secret_rotated_at?->toISOString(),
            'deliveriesCount' => $webhook->deliveries_count ?? 0,
            'health' => $canManageIntegrations ? $this->deliveryHealth($webhook) : null,
        ];
    }

    /**
     * @return array{
     *     counts: array{pending: int, delivered: int, failed: int, cancelled: int},
     *     latestState: string|null
     * }
     */
    private function deliveryHealth(Webhook $webhook): array
    {
        $counts = [
            'pending' => 0,
            'delivered' => 0,
            'failed' => 0,
            'cancelled' => 0,
        ];
        $latestId = 0;
        $latestState = null;
        $states = $webhook->deliveries()
            ->where('revision', $webhook->revision)
            ->selectRaw('state, COUNT(*) AS state_count, MAX(id) AS latest_id')
            ->groupBy('state')
            ->get();

        foreach ($states as $state) {
            $stateName = (string) $state->state;
            if (array_key_exists($stateName, $counts)) {
                $counts[$stateName] = (int) $state->state_count;
            }

            if ((int) $state->latest_id > $latestId) {
                $latestId = (int) $state->latest_id;
                $latestState = $stateName;
            }
        }

        return [
            'counts' => $counts,
            'latestState' => $latestState,
        ];
    }
}
