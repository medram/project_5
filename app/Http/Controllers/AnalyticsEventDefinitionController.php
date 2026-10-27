<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAnalyticsEventDefinitionRequest;
use App\Models\AnalyticsEventDefinition;
use App\Services\Workspaces\WorkspaceContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AnalyticsEventDefinitionController extends Controller
{
    public function __construct(private readonly WorkspaceContext $workspaces) {}

    public function index(Request $request): Response
    {
        $workspace = $this->workspaces->currentFor($request->user());
        Gate::authorize('manageIntegrations', $workspace);

        return Inertia::render('analytics/custom-events', [
            'definitions' => $workspace->analyticsEventDefinitions()
                ->orderBy('event_name')
                ->get(['id', 'event_name', 'is_active'])
                ->map(fn (AnalyticsEventDefinition $definition): array => [
                    'id' => (int) $definition->id,
                    'event_name' => $definition->event_name,
                    'is_active' => $definition->is_active,
                ])
                ->values()
                ->all(),
        ]);
    }

    public function store(StoreAnalyticsEventDefinitionRequest $request): RedirectResponse
    {
        $workspace = $this->workspaces->currentFor($request->user());
        $workspace->analyticsEventDefinitions()->create([
            'event_name' => $request->validated('event_name'),
            'is_active' => true,
            'created_by' => $request->user()->id,
        ]);

        return to_route('analytics.custom-events.index');
    }

    public function disable(Request $request, AnalyticsEventDefinition $definition): RedirectResponse
    {
        $workspace = $this->workspaces->currentFor($request->user());
        Gate::authorize('manageIntegrations', $workspace);

        $workspace->analyticsEventDefinitions()
            ->findOrFail($definition->getKey())
            ->update(['is_active' => false]);

        return to_route('analytics.custom-events.index');
    }
}
