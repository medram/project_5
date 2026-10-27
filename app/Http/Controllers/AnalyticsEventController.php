<?php

namespace App\Http\Controllers;

use App\Http\Requests\AnalyticsFilterRequest;
use App\Models\AnalyticsEvent;
use App\Services\Analytics\AnalyticsInsights;
use App\Services\Workspaces\WorkspaceContext;
use Inertia\Inertia;
use Inertia\Response;

class AnalyticsEventController extends Controller
{
    public function __construct(
        private readonly AnalyticsInsights $analytics,
        private readonly WorkspaceContext $workspaceContext,
    ) {}

    public function __invoke(AnalyticsFilterRequest $request): Response
    {
        $workspace = $this->workspaceContext->currentFor($request->user());
        $filters = $request->filters();
        $events = $this->analytics->eventRowsForWorkspace($workspace, $filters)
            ->select(AnalyticsInsights::EVENT_ROW_COLUMNS)
            ->orderByDesc('occurred_at')
            ->orderByDesc('uuid')
            ->paginate(50)
            ->withQueryString();

        return Inertia::render('analytics/events', [
            'filters' => $filters,
            'events' => $events->through(
                static fn (AnalyticsEvent $event): array => $event->toArray(),
            ),
        ]);
    }
}
