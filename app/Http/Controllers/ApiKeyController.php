<?php

namespace App\Http\Controllers;

use App\Enums\PlanMetric;
use App\Http\Requests\StoreApiKeyRequest;
use App\Models\WorkspaceApiToken;
use App\Services\Billing\EnforcesPlanLimits;
use App\Services\Workspaces\WorkspaceContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ApiKeyController extends Controller
{
    public function __construct(
        private readonly WorkspaceContext $workspaceContext,
        private readonly EnforcesPlanLimits $planLimits,
    ) {}

    public function index(Request $request): Response
    {
        $workspace = $this->workspaceContext->currentFor($request->user());
        Gate::authorize('manageIntegrations', $workspace);

        return Inertia::render('settings/api-keys/index', [
            'keys' => WorkspaceApiToken::query()
                ->where('workspace_id', $workspace->getKey())
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->get(['id', 'name', 'abilities', 'created_at', 'expires_at', 'last_used_at'])
                ->map(fn (WorkspaceApiToken $token): array => [
                    'id' => (int) $token->getKey(),
                    'name' => $token->name,
                    'abilities' => $token->abilities ?? [],
                    'createdAt' => $token->created_at?->toISOString(),
                    'expiresAt' => $token->expires_at?->toISOString(),
                    'lastUsedAt' => $token->last_used_at?->toISOString(),
                ])
                ->values()
                ->all(),
            'availableAbilities' => collect(StoreApiKeyRequest::AVAILABLE_ABILITIES)
                ->map(fn (string $label, string $ability): array => [
                    'value' => $ability,
                    'label' => $label,
                ])
                ->values()
                ->all(),
            'oneTimeToken' => (int) session('apiKeyWorkspaceId') === (int) $workspace->getKey()
                ? session('apiKeyToken')
                : null,
        ]);
    }

    public function store(StoreApiKeyRequest $request): RedirectResponse
    {
        $workspace = $this->workspaceContext->currentFor($request->user());
        Gate::authorize('manageIntegrations', $workspace);
        $attributes = $request->validated();

        $token = $this->planLimits->create(
            $workspace,
            PlanMetric::ApiKeys,
            fn () => $request->user()->createWorkspaceApiToken(
                $workspace,
                $attributes['name'],
                $attributes['abilities'],
                isset($attributes['expires_at']) ? Carbon::parse($attributes['expires_at']) : null,
            ),
        );

        return to_route('api-keys.index')->with([
            'apiKeyToken' => $token->plainTextToken,
            'apiKeyWorkspaceId' => $workspace->getKey(),
        ]);
    }

    public function destroy(Request $request, int $token): RedirectResponse
    {
        $workspace = $this->workspaceContext->currentFor($request->user());
        Gate::authorize('manageIntegrations', $workspace);

        WorkspaceApiToken::query()
            ->where('workspace_id', $workspace->getKey())
            ->findOrFail($token)
            ->delete();

        return to_route('api-keys.index');
    }
}
