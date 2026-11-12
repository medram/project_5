<?php

namespace App\Services\Analytics;

use App\Models\AnalyticsEvent;
use App\Models\Click;
use App\Models\Link;
use App\Models\Workspace;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

class AnalyticsInsights
{
    public const EVENT_ROW_COLUMNS = [
        'uuid',
        'event_name',
        'occurred_at',
        'link_id',
        'bio_page_id',
        'bio_link_id',
        'link_page_id',
        'link_page_variant_id',
        'link_page_item_id',
        'link_overlay_id',
        'country_code',
        'browser',
        'platform',
        'referrer_host',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
    ];

    private const RESOURCE_COLUMNS = [
        'link' => 'link_id',
        'bio_page' => 'bio_page_id',
        'bio_link' => 'bio_link_id',
        'link_page' => 'link_page_id',
        'link_page_variant' => 'link_page_variant_id',
        'link_page_item' => 'link_page_item_id',
        'link_overlay' => 'link_overlay_id',
    ];

    /**
     * @return array{
     *     days: 7|30|90,
     *     totalClicks: int,
     *     uniqueVisitors: int,
     *     totalEvents: int,
     *     daily: list<array{date: string, clicks: int, events: int}>,
     *     eventsByName: list<array{name: string, count: int}>,
     *     topReferrers: list<array{name: string, count: int}>,
     *     topCountries: list<array{name: string, count: int}>,
     *     topBrowsers: list<array{name: string, count: int}>,
     *     topPlatforms: list<array{name: string, count: int}>
     * }
     */
    public function forWorkspace(Workspace $workspace, int $days): array
    {
        return $this->result(
            Click::query()->whereHas(
                'link',
                fn (Builder $links): Builder => $links->where('workspace_id', $workspace->getKey()),
            ),
            AnalyticsEvent::query()->where('workspace_id', $workspace->getKey()),
            $days,
        );
    }

    /**
     * @param array{
     *     days: int,
     *     resource_type: ?string,
     *     resource_id: ?int,
     *     event_name: ?string,
     *     browser: ?string,
     *     platform: ?string,
     *     country: ?string,
     *     utm_source: ?string,
     *     utm_medium: ?string,
     *     utm_campaign: ?string,
     *     utm_term: ?string,
     *     utm_content: ?string
     * } $filters
     * @return array{
     *     days: 7|30|90,
     *     totalClicks: int,
     *     uniqueVisitors: int,
     *     totalEvents: int,
     *     daily: list<array{date: string, clicks: int, events: int}>,
     *     eventsByName: list<array{name: string, count: int}>,
     *     topReferrers: list<array{name: string, count: int}>,
     *     topCountries: list<array{name: string, count: int}>,
     *     topBrowsers: list<array{name: string, count: int}>,
     *     topPlatforms: list<array{name: string, count: int}>
     * }
     */
    public function forFilteredWorkspace(Workspace $workspace, array $filters): array
    {
        return $this->result(
            $this->clickQueryForWorkspace($workspace, $filters),
            $this->eventQueryForWorkspace($workspace, $filters),
            $filters['days'],
        );
    }

    /**
     * @param array{
     *     days: int,
     *     resource_type: ?string,
     *     resource_id: ?int,
     *     event_name: ?string,
     *     browser: ?string,
     *     platform: ?string,
     *     country: ?string,
     *     utm_source: ?string,
     *     utm_medium: ?string,
     *     utm_campaign: ?string,
     *     utm_term: ?string,
     *     utm_content: ?string
     * } $filters
     * @return Builder<AnalyticsEvent>
     */
    public function eventRowsForWorkspace(Workspace $workspace, array $filters): Builder
    {
        [$start, $end] = $this->windowBounds($filters['days']);

        return $this->withinWindow(
            $this->eventQueryForWorkspace($workspace, $filters),
            $start,
            $end,
        );
    }

    /**
     * @return array{
     *     days: 7|30|90,
     *     totalClicks: int,
     *     uniqueVisitors: int,
     *     totalEvents: int,
     *     daily: list<array{date: string, clicks: int, events: int}>,
     *     eventsByName: list<array{name: string, count: int}>,
     *     topReferrers: list<array{name: string, count: int}>,
     *     topCountries: list<array{name: string, count: int}>,
     *     topBrowsers: list<array{name: string, count: int}>,
     *     topPlatforms: list<array{name: string, count: int}>
     * }
     */
    public function forLink(Link $link, int $days): array
    {
        return $this->result(
            $link->clicks()->getQuery(),
            AnalyticsEvent::query()
                ->where('user_id', $link->user_id)
                ->where('link_id', $link->id),
            $days,
        );
    }

    /**
     * @param  Builder<Click>  $clicks
     * @param  Builder<AnalyticsEvent>  $events
     * @return array{
     *     days: 7|30|90,
     *     totalClicks: int,
     *     uniqueVisitors: int,
     *     totalEvents: int,
     *     daily: list<array{date: string, clicks: int, events: int}>,
     *     eventsByName: list<array{name: string, count: int}>,
     *     topReferrers: list<array{name: string, count: int}>,
     *     topCountries: list<array{name: string, count: int}>,
     *     topBrowsers: list<array{name: string, count: int}>,
     *     topPlatforms: list<array{name: string, count: int}>
     * }
     */
    private function result(Builder $clicks, Builder $events, int $days): array
    {
        [$start, $end] = $this->windowBounds($days);
        $windowedClicks = $this->withinWindow($clicks, $start, $end);
        $windowedEvents = $this->withinWindow($events, $start, $end);
        $clickCounts = $this->dailyCounts($windowedClicks);
        $eventCounts = $this->dailyCounts($windowedEvents);
        $daily = [];

        for ($offset = 0; $offset < $days; $offset++) {
            $date = $start->copy()->addDays($offset)->toDateString();
            $daily[] = [
                'date' => $date,
                'clicks' => $clickCounts[$date] ?? 0,
                'events' => $eventCounts[$date] ?? 0,
            ];
        }

        return [
            'days' => $days,
            'totalClicks' => (clone $windowedClicks)->count(),
            'uniqueVisitors' => (clone $windowedEvents)
                ->distinct()
                ->count('visitor_hash'),
            'totalEvents' => (clone $windowedEvents)->count(),
            'daily' => $daily,
            'eventsByName' => $this->groupedCounts($windowedEvents, 'event_name'),
            'topReferrers' => $this->groupedCounts($windowedEvents, 'referrer_host', 5),
            'topCountries' => $this->groupedCounts($windowedEvents, 'country_code', 5),
            'topBrowsers' => $this->groupedCounts($windowedEvents, 'browser', 5),
            'topPlatforms' => $this->groupedCounts($windowedEvents, 'platform', 5),
        ];
    }

    /**
     * @param array{
     *     resource_type: ?string,
     *     resource_id: ?int,
     *     event_name: ?string,
     *     browser: ?string,
     *     platform: ?string,
     *     country: ?string,
     *     utm_source: ?string,
     *     utm_medium: ?string,
     *     utm_campaign: ?string,
     *     utm_term: ?string,
     *     utm_content: ?string
     * } $filters
     * @return Builder<Click>
     */
    private function clickQueryForWorkspace(Workspace $workspace, array $filters): Builder
    {
        $clicks = Click::query()->whereHas(
            'link',
            fn (Builder $links): Builder => $links->where('workspace_id', $workspace->getKey()),
        );

        if ($filters['resource_type'] !== null) {
            if ($filters['resource_type'] !== 'link') {
                return $clicks->whereRaw('1 = 0');
            }

            $clicks->where('link_id', $filters['resource_id']);
        }

        return $clicks;
    }

    /**
     * @param array{
     *     resource_type: ?string,
     *     resource_id: ?int,
     *     event_name: ?string,
     *     browser: ?string,
     *     platform: ?string,
     *     country: ?string,
     *     utm_source: ?string,
     *     utm_medium: ?string,
     *     utm_campaign: ?string,
     *     utm_term: ?string,
     *     utm_content: ?string
     * } $filters
     * @return Builder<AnalyticsEvent>
     */
    private function eventQueryForWorkspace(Workspace $workspace, array $filters): Builder
    {
        $events = AnalyticsEvent::query()->where('workspace_id', $workspace->getKey());

        if ($filters['resource_type'] !== null) {
            $events->where(
                self::RESOURCE_COLUMNS[$filters['resource_type']],
                $filters['resource_id'],
            );
        }

        foreach ([
            'event_name' => 'event_name',
            'browser' => 'browser',
            'platform' => 'platform',
            'country' => 'country_code',
            'utm_source' => 'utm_source',
            'utm_medium' => 'utm_medium',
            'utm_campaign' => 'utm_campaign',
            'utm_term' => 'utm_term',
            'utm_content' => 'utm_content',
        ] as $filter => $column) {
            if ($filters[$filter] !== null) {
                $events->where($column, $filters[$filter]);
            }
        }

        return $events;
    }

    /** @return array{0: CarbonInterface, 1: CarbonInterface} */
    private function windowBounds(int $days): array
    {
        if (! in_array($days, [7, 30, 90], true)) {
            throw new InvalidArgumentException("Unsupported analytics window [{$days}].");
        }

        $end = now('UTC')->startOfDay()->addDay();

        return [$end->copy()->subDays($days), $end];
    }

    /**
     * @param  Builder<Click>|Builder<AnalyticsEvent>  $query
     * @return Builder<Click>|Builder<AnalyticsEvent>
     */
    private function withinWindow(Builder $query, CarbonInterface $start, CarbonInterface $end): Builder
    {
        return (clone $query)
            ->where('occurred_at', '>=', $start)
            ->where('occurred_at', '<', $end);
    }

    /**
     * @param  Builder<Click>|Builder<AnalyticsEvent>  $query
     * @return array<string, int>
     */
    private function dailyCounts(Builder $query): array
    {
        return (clone $query)
            ->selectRaw('DATE(occurred_at) as aggregate_date, COUNT(*) as aggregate_count')
            ->groupByRaw('DATE(occurred_at)')
            ->get()
            ->mapWithKeys(fn ($row): array => [
                (string) $row->getAttribute('aggregate_date') => (int) $row->getAttribute('aggregate_count'),
            ])
            ->all();
    }

    /**
     * @param  Builder<AnalyticsEvent>  $events
     * @return list<array{name: string, count: int}>
     */
    private function groupedCounts(Builder $events, string $column, ?int $limit = null): array
    {
        $query = (clone $events)
            ->whereNotNull($column)
            ->where($column, '<>', '')
            ->select($column)
            ->selectRaw('COUNT(*) as aggregate_count')
            ->groupBy($column)
            ->orderByDesc('aggregate_count')
            ->orderBy($column);

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query
            ->get()
            ->map(fn (AnalyticsEvent $event): array => [
                'name' => (string) $event->getAttribute($column),
                'count' => (int) $event->getAttribute('aggregate_count'),
            ])
            ->values()
            ->all();
    }
}
