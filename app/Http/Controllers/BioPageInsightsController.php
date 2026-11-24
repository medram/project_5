<?php

namespace App\Http\Controllers;

use App\Http\Middleware\ScopedAdminContent;
use App\Models\AnalyticsEvent;
use App\Models\BioPage;
use App\Services\Admin\AdminContentNavigation;
use App\Services\BioPages\BioPagePresentation;
use App\Services\Platform\AdminContentAccess;
use App\Services\Workspaces\WorkspaceContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BioPageInsightsController extends Controller
{
    public function __construct(
        private readonly WorkspaceContext $workspaceContext,
        private readonly BioPagePresentation $presentation,
        private readonly AdminContentNavigation $navigation,
    ) {}

    public function __invoke(Request $request, BioPage $bioPage): Response
    {
        $workspace = $this->workspaceContext->forContentRequest($request);
        $bioPage = BioPage::query()
            ->where('workspace_id', $workspace->getKey())
            ->whereKey($bioPage->getKey())
            ->firstOrFail();
        app(AdminContentAccess::class)->authorize($request, 'view', $bioPage);
        $admin = $request->attributes->get(ScopedAdminContent::ATTRIBUTE);
        $days = $this->selectedDays($request);
        $presentation = $this->presentation->make(
            $bioPage,
            true,
            is_array($admin) ? fn ($page, $block) => route('admin.content.biolinks.blocks.preview-image', [$page, $block]) : null,
        );
        $bioPage->setAttribute('appearance', $presentation['appearance']);

        $props = [
            'bioPage' => $bioPage,
            'bioLinks' => $bioPage->bioLinks()->get(),
            'contentBlocks' => array_values(array_filter(
                $presentation['items'],
                fn (array $item): bool => $item['type'] !== 'link',
            )),
            'previewAt' => now('UTC')->toISOString(),
            'insights' => $this->insightsForPage($bioPage, $days),
        ];

        if (is_array($admin)) {
            $props['backUrl'] = $this->navigation->withListQuery($request, $admin['list_url']);
            $props['editorUrl'] = $admin['editor_url'];
        }

        return Inertia::render(
            is_array($admin) ? 'admin/content/bio-edit' : 'bio-pages/edit',
            $props,
        );
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
     *     daily: list<array{date: string, views: int, clicks: int}>,
     *     topLinks: list<array{name: string, count: int}>,
     *     topReferrers: list<array{name: string, count: int}>,
     *     topCountries: list<array{name: string, count: int}>,
     *     topBrowsers: list<array{name: string, count: int}>,
     *     topPlatforms: list<array{name: string, count: int}>
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

        $events = $this->eventQuery($bioPage, $start, $end)
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
            'topLinks' => $this->topLinks($bioPage, $start, $end),
            'topReferrers' => $this->groupedEvents($bioPage, $start, $end, 'referrer_host'),
            'topCountries' => $this->groupedEvents($bioPage, $start, $end, 'country_code'),
            'topBrowsers' => $this->groupedEvents($bioPage, $start, $end, 'browser'),
            'topPlatforms' => $this->groupedEvents($bioPage, $start, $end, 'platform'),
        ];
    }

    /**
     * @return Builder<AnalyticsEvent>
     */
    private function eventQuery(BioPage $bioPage, \DateTimeInterface $start, \DateTimeInterface $end): Builder
    {
        return AnalyticsEvent::query()
            ->where('analytics_events.workspace_id', $bioPage->workspace_id)
            ->where('analytics_events.bio_page_id', $bioPage->getKey())
            ->whereIn('analytics_events.event_name', ['bio_page.view', 'bio_page.clicked'])
            ->where('analytics_events.occurred_at', '>=', $start)
            ->where('analytics_events.occurred_at', '<', $end);
    }

    /**
     * @return list<array{name: string, count: int}>
     */
    private function groupedEvents(
        BioPage $bioPage,
        \DateTimeInterface $start,
        \DateTimeInterface $end,
        string $column,
    ): array {
        if (! in_array($column, ['referrer_host', 'country_code', 'browser', 'platform'], true)) {
            return [];
        }

        return $this->eventQuery($bioPage, $start, $end)
            ->whereNotNull('analytics_events.'.$column)
            ->where('analytics_events.'.$column, '<>', '')
            ->select('analytics_events.'.$column)
            ->selectRaw('COUNT(*) as aggregate_count')
            ->groupBy('analytics_events.'.$column)
            ->orderByDesc('aggregate_count')
            ->orderBy('analytics_events.'.$column)
            ->limit(5)
            ->get()
            ->map(fn (AnalyticsEvent $event): array => [
                'name' => (string) $event->getAttribute($column),
                'count' => (int) $event->getAttribute('aggregate_count'),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{name: string, count: int}>
     */
    private function topLinks(BioPage $bioPage, \DateTimeInterface $start, \DateTimeInterface $end): array
    {
        return $this->eventQuery($bioPage, $start, $end)
            ->join('bio_links', 'bio_links.id', '=', 'analytics_events.bio_link_id')
            ->where('analytics_events.event_name', 'bio_page.clicked')
            ->select('bio_links.title as aggregate_name')
            ->selectRaw('COUNT(*) as aggregate_count')
            ->groupBy('bio_links.id', 'bio_links.title')
            ->orderByDesc('aggregate_count')
            ->orderBy('bio_links.title')
            ->limit(5)
            ->get()
            ->map(fn (AnalyticsEvent $event): array => [
                'name' => (string) $event->getAttribute('aggregate_name'),
                'count' => (int) $event->getAttribute('aggregate_count'),
            ])
            ->values()
            ->all();
    }
}
