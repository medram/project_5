<?php

namespace App\Http\Controllers;

use App\Models\Link;
use App\Services\Analytics\AnalyticsInsights;
use App\Services\Workspaces\WorkspaceContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class LinkInsightsController extends Controller
{
    public function __construct(
        private readonly AnalyticsInsights $analytics,
        private readonly WorkspaceContext $workspaceContext,
    ) {}

    public function __invoke(Request $request, Link $link): Response
    {
        Gate::authorize('view', $link);
        $workspace = $this->workspaceContext->currentFor($request->user());
        $link = $workspace->links()->findOrFail($link->getKey());

        $link->load(
            'folder:id,name',
            'customDomain:id,hostname',
            'linkOverlay:id,name',
            'linkPage:id,name,slug',
            'tags:id,name,slug,color',
            'routingRules',
        )->loadCount('clicks');

        return Inertia::render('links/insights', [
            'link' => $link,
            'analytics' => $this->analytics->forLink(
                $link,
                $this->selectedDays($request),
            ),
        ]);
    }

    private function selectedDays(Request $request): int
    {
        $days = filter_var($request->query('days', 30), FILTER_VALIDATE_INT);

        abort_unless(is_int($days) && in_array($days, [7, 30, 90], true), 422);

        return $days;
    }
}
