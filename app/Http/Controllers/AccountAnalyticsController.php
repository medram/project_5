<?php

namespace App\Http\Controllers;

use App\Http\Requests\AnalyticsFilterRequest;
use App\Services\Analytics\AnalyticsInsights;
use App\Services\Workspaces\WorkspaceContext;
use Inertia\Inertia;
use Inertia\Response;

class AccountAnalyticsController extends Controller
{
    public function __construct(
        private readonly AnalyticsInsights $analytics,
        private readonly WorkspaceContext $workspaceContext,
    ) {}

    public function __invoke(AnalyticsFilterRequest $request): Response
    {
        $workspace = $this->workspaceContext->currentFor($request->user());
        $filters = $request->filters();

        return Inertia::render('analytics/index', [
            'analytics' => $this->analytics->forFilteredWorkspace($workspace, $filters),
            'canManageCustomEvents' => $request->user()->can('manageIntegrations', $workspace),
            'filters' => $filters,
        ]);
    }
}
