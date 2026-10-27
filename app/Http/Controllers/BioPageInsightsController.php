<?php

namespace App\Http\Controllers;

use App\Models\AnalyticsEvent;
use App\Models\BioPage;
use App\Services\BioPages\BioPagePresentation;
use App\Services\Workspaces\WorkspaceContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class BioPageInsightsController extends Controller
{
    public function __construct(
        private readonly WorkspaceContext $workspaceContext,
        private readonly BioPagePresentation $presentation,
    ) {}

    public function __invoke(Request $request, BioPage $bioPage): Response
    {
        $workspace = $this->workspaceContext->currentFor($request->user());
        $bioPage = BioPage::query()
            ->where('workspace_id', $workspace->getKey())
            ->whereKey($bioPage->getKey())
            ->firstOrFail();
        Gate::authorize('view', $bioPage);
        $days = $this->selectedDays($request);
        $presentation = $this->presentation->make($bioPage, true);

        return Inertia::render('bio-pages/edit', [
            'bioPage' => $bioPage,
            'bioLinks' => $bioPage->bioLinks()->get(),
            'contentBlocks' => array_values(array_filter(
                $presentation['items'],
                fn (array $item): bool => $item['type'] !== 'link',
            )),
            'previewAt' => now('UTC')->toISOString(),
            'insights' => $this->insightsForPage($bioPage, $days),
        ]);
    }

    private function selectedDays(Request $request): int
    {
        $days = filter_var($request->query('days', 30), FILTER_VALIDATE_INT);

        abort_unless(is_int($days) && in_array($days, [7, 30, 90], true), 422);

        return $days;
    }

    /**
     * @return array{
     *     days: int,
     *     startDate: string,
     *     endDate: string,
     *     totalViews: int,
     *     totalClicks: int,
     *     daily: list<array{date: string, views: int, clicks: int}>
     * }
     */
    private function insightsForPage(BioPage $bioPage, int $days): array
    {
        $end = now('UTC')->startOfDay()->addDay();
        $start = $end->copy()->subDays($days);
        /** @var array<string, array{date: string, views: int, clicks: int}> $dailyByDate */
        $dailyByDate = [];

        for ($offset = 0; $offset < $days; $offset++) {
            $date = $start->copy()->addDays($offset)->toDateString();
            $dailyByDate[$date] = [
                'date' => $date,
                'views' => 0,
                'clicks' => 0,
            ];
        }

        $events = AnalyticsEvent::query()
            ->where('workspace_id', $bioPage->workspace_id)
            ->where('bio_page_id', $bioPage->getKey())
            ->whereIn('event_name', ['bio_page.view', 'bio_page.clicked'])
            ->where('occurred_at', '>=', $start)
            ->where('occurred_at', '<', $end)
            ->selectRaw('DATE(occurred_at) as aggregate_date, event_name, COUNT(*) as aggregate_count')
            ->groupByRaw('DATE(occurred_at), event_name')
            ->get();
        $totalViews = 0;
        $totalClicks = 0;

        foreach ($events as $event) {
            $date = (string) $event->getAttribute('aggregate_date');
            if (! isset($dailyByDate[$date])) {
                continue;
            }

            $count = (int) $event->getAttribute('aggregate_count');
            if ($event->event_name === 'bio_page.view') {
                $dailyByDate[$date]['views'] = $count;
                $totalViews += $count;
            } else {
                $dailyByDate[$date]['clicks'] = $count;
                $totalClicks += $count;
            }
        }

        return [
            'days' => $days,
            'startDate' => $start->toDateString(),
            'endDate' => $end->copy()->subDay()->toDateString(),
            'totalViews' => $totalViews,
            'totalClicks' => $totalClicks,
            'daily' => array_values($dailyByDate),
        ];
    }
}
