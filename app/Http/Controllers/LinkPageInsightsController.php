<?php

namespace App\Http\Controllers;

use App\Http\Middleware\ScopedAdminContent;
use App\Models\AnalyticsEvent;
use App\Models\LinkPage;
use App\Services\Admin\AdminContentNavigation;
use App\Services\Platform\AdminContentAccess;
use App\Services\Workspaces\WorkspaceContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LinkPageInsightsController extends Controller
{
    public function __construct(
        private readonly WorkspaceContext $workspaces,
        private readonly AdminContentNavigation $navigation,
    ) {}

    public function __invoke(Request $request, LinkPage $linkPage): Response
    {
        $workspace = $this->workspaces->forContentRequest($request);
        abort_unless((int) $linkPage->workspace_id === (int) $workspace->id, 404);
        app(AdminContentAccess::class)->authorize($request, 'view', $linkPage);
        $days = filter_var($request->query('days', 30), FILTER_VALIDATE_INT);
        abort_unless(is_int($days) && in_array($days, [7, 30, 90], true), 422);
        $admin = $request->attributes->get(ScopedAdminContent::ATTRIBUTE);

        $props = [
            'linkPage' => [
                'id' => $linkPage->id,
                'name' => $linkPage->name,
                'slug' => $linkPage->slug,
                'publicUrl' => $linkPage->is_published ? url("/pages/{$linkPage->slug}") : null,
            ],
            'backUrl' => is_array($admin)
                ? $this->navigation->withListQuery($request, $admin['list_url'])
                : route('link-pages.show', $linkPage),
            'backLabel' => is_array($admin) ? 'Link pages' : 'Edit page',
            'windowBaseUrl' => is_array($admin)
                ? $this->navigation->withListQuery($request, route('admin.content.link-pages.insights', $linkPage, false))
                : route('link-pages.insights', $linkPage),
            'insights' => $this->insightsForPage($linkPage, $days),
        ];

        if (is_array($admin)) {
            $props['editUrl'] = $this->navigation->withListQuery(
                $request,
                route('admin.content.link-pages.edit', $linkPage, false),
            );
        }

        return Inertia::render(
            is_array($admin) ? 'admin/content/link-page-insights' : 'link-pages/insights',
            $props,
        );
    }

    /** @return array<string, mixed> */
    private function insightsForPage(LinkPage $linkPage, int $days): array
    {
        $end = now('UTC')->startOfDay()->addDay();
        $start = $end->copy()->subDays($days);
        $variants = $linkPage->variants()
            ->withTrashed()
            ->orderByDesc('is_control')
            ->orderBy('id')
            ->get();
        $dailyByVariant = [];

        foreach ($variants as $variant) {
            $dailyByDate = [];
            for ($offset = 0; $offset < $days; $offset++) {
                $date = $start->copy()->addDays($offset)->toDateString();
                $dailyByDate[$date] = [
                    'date' => $date,
                    'views' => 0,
                    'clicks' => 0,
                    'leads' => 0,
                ];
            }
            $dailyByVariant[$variant->id] = [
                'views' => 0,
                'clicks' => 0,
                'leads' => 0,
                'daily' => $dailyByDate,
            ];
        }

        $counterByEvent = [
            'link_page.view' => 'views',
            'link_page.clicked' => 'clicks',
            'link_page.lead_submitted' => 'leads',
        ];
        $events = AnalyticsEvent::query()
            ->where('workspace_id', $linkPage->workspace_id)
            ->where('link_page_id', $linkPage->getKey())
            ->whereNotNull('link_page_variant_id')
            ->whereIn('event_name', array_keys($counterByEvent))
            ->where('occurred_at', '>=', $start)
            ->where('occurred_at', '<', $end)
            ->selectRaw('link_page_variant_id, DATE(occurred_at) as aggregate_date, event_name, COUNT(*) as aggregate_count')
            ->groupBy('link_page_variant_id', 'aggregate_date', 'event_name')
            ->get();
        $totals = ['views' => 0, 'clicks' => 0, 'leads' => 0];

        foreach ($events as $event) {
            $variantId = (int) $event->link_page_variant_id;
            $date = (string) $event->getAttribute('aggregate_date');
            if (! isset($dailyByVariant[$variantId]['daily'][$date])) {
                continue;
            }

            $counter = $counterByEvent[$event->event_name];
            $count = (int) $event->getAttribute('aggregate_count');
            $dailyByVariant[$variantId][$counter] += $count;
            $dailyByVariant[$variantId]['daily'][$date][$counter] += $count;
            $totals[$counter] += $count;
        }

        return [
            'days' => $days,
            'startDate' => $start->toDateString(),
            'endDate' => $end->copy()->subDay()->toDateString(),
            'totals' => $totals,
            'variants' => $variants->map(fn ($variant): array => [
                'id' => $variant->id,
                'name' => $variant->name,
                'weight' => $variant->weight,
                'isControl' => $variant->is_control,
                'isActive' => $variant->is_active && ! $variant->trashed(),
                'isDeleted' => $variant->trashed(),
                ...$dailyByVariant[$variant->id],
                'daily' => array_values($dailyByVariant[$variant->id]['daily']),
            ])->values()->all(),
        ];
    }
}
