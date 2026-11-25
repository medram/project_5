<?php

namespace App\Services\Admin;

use App\Models\AnalyticsEvent;
use App\Models\BioPage;
use App\Models\Click;
use App\Models\ContentMedia;
use App\Models\CustomDomain;
use App\Models\Folder;
use App\Models\Link;
use App\Models\LinkPage;
use App\Models\PlanStripePrice;
use App\Models\QrCode;
use App\Models\QrScan;
use App\Models\TrackingPixel;
use App\Models\User;
use App\Models\Webhook;
use App\Models\Workspace;
use App\Models\WorkspaceApiToken;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Laravel\Cashier\Subscription;

class AdminInsights
{
    public const K_ANONYMITY_THRESHOLD = 5;

    /**
     * @return array<string, mixed>
     */
    public function platform(AdminInsightsRange $range): array
    {
        $accounts = [
            'total' => User::query()->count(),
            'new' => $this->inRange(User::query(), 'created_at', $range)->count(),
            'active' => User::query()->where('is_suspended', false)->whereNull('deletion_requested_at')->count(),
            'suspended' => User::query()->where('is_suspended', true)->count(),
            'deletionPending' => User::query()->whereNotNull('deletion_requested_at')->count(),
        ];

        $workspaces = [
            'total' => Workspace::query()->count(),
            'new' => $this->inRange(Workspace::query(), 'created_at', $range)->count(),
        ];

        return [
            'range' => $range->toArray(),
            'accounts' => $accounts,
            'workspaces' => $workspaces,
            'subscriptions' => $this->subscriptions(),
            'resources' => [
                'links' => Link::query()->count(),
                'folders' => Folder::query()->count(),
                'qrCampaigns' => QrCode::query()->count(),
                'bioPages' => BioPage::query()->count(),
                'domains' => CustomDomain::query()->count(),
                'pixels' => TrackingPixel::query()->count(),
                'members' => DB::table('workspace_user')->count(),
                'webhooks' => Webhook::query()->count(),
                'linkPages' => LinkPage::query()->count(),
                'apiKeys' => WorkspaceApiToken::query()->count(),
                'storageBytes' => (int) ContentMedia::query()->sum('byte_size'),
            ],
            'engagement' => [
                'clicks' => $this->inRange(Click::query(), 'occurred_at', $range)->count(),
                'scans' => $this->inRange(QrScan::query(), 'scanned_at', $range)->count(),
                'events' => $this->eventQuery($range)->count(),
            ],
            'daily' => $this->platformDaily($range),
            'dimensions' => $this->dimensions($range),
            'kAnonymityThreshold' => self::K_ANONYMITY_THRESHOLD,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function visitors(AdminInsightsRange $range): array
    {
        $events = $this->eventQuery($range);

        return [
            'range' => $range->toArray(),
            'totals' => [
                'events' => (clone $events)->count(),
                'uniqueVisitors' => (clone $events)->distinct()->count('visitor_hash'),
                'clicks' => $this->inRange(Click::query(), 'occurred_at', $range)->count(),
                'scans' => $this->inRange(QrScan::query(), 'scanned_at', $range)->count(),
                'pageViews' => (clone $events)->whereIn('event_name', ['link_page.view', 'bio_page.view', 'overlay.view'])->count(),
            ],
            'daily' => $this->visitorDaily($range),
            'eventSeries' => $this->eventSeries($events),
            'dimensions' => $this->dimensions($range),
            'kAnonymityThreshold' => self::K_ANONYMITY_THRESHOLD,
        ];
    }

    /**
     * @return array{states: list<array{state: string, count: int}>, mrrCents: int, arrCents: int, mappedSubscriptions: int, unmappedSubscriptions: int, source: string}
     */
    private function subscriptions(): array
    {
        $states = Subscription::query()
            ->select('stripe_status')
            ->selectRaw('COUNT(*) as aggregate_count')
            ->groupBy('stripe_status')
            ->orderBy('stripe_status')
            ->get()
            ->map(fn (Subscription $subscription): array => [
                'state' => (string) $subscription->stripe_status,
                'count' => (int) $subscription->getAttribute('aggregate_count'),
            ])
            ->values()
            ->all();

        /** @var list<array{state: string, count: int}> $states */
        $states = array_values($states);
        $paypalStates = DB::table('paypal_subscriptions')
            ->select('status')->selectRaw('COUNT(*) as aggregate_count')
            ->groupBy('status')->orderBy('status')->get();
        foreach ($paypalStates as $state) {
            $states[] = [
                'state' => 'PayPal: '.strtolower(str_replace('_', ' ', $state->status)),
                'count' => (int) $state->aggregate_count,
            ];
        }

        $pricesById = PlanStripePrice::query()->with('plan')->get()->keyBy('stripe_price_id');
        $currency = strtolower((string) config('cashier.currency', 'usd'));

        $mrr = 0;
        $mapped = 0;
        $unmapped = 0;
        $subscriptions = Subscription::query()
            ->with('items')
            ->where(fn (Builder $query) => $query
                ->whereIn('stripe_status', ['active', 'trialing'])
                ->orWhere(fn (Builder $ended) => $ended
                    ->whereNotNull('ends_at')
                    ->where('ends_at', '>', now())))
            ->get();

        foreach ($subscriptions as $subscription) {
            $prices = collect([$subscription->stripe_price])
                ->merge($subscription->items->pluck('stripe_price'))
                ->filter(fn (mixed $price): bool => is_string($price) && $price !== '')
                ->unique()
                ->values();

            $mapping = $prices->map(fn (string $price) => $pricesById[$price] ?? null)->first(fn ($entry) => $entry !== null);

            if ($mapping !== null && $mapping->verified_at !== null && $mapping->unit_amount !== null
                && strtolower($mapping->currency) === $currency
                && strtolower($mapping->plan->currency) === $currency) {
                $mrr += $mapping->interval === 'year'
                    ? (int) round($mapping->unit_amount / 12)
                    : $mapping->unit_amount;
                $mapped++;
            } else {
                $unmapped++;
            }
        }

        $paypalPrices = DB::table('paypal_subscriptions')
            ->join('plan_paypal_prices', 'paypal_subscriptions.plan_paypal_price_id', '=', 'plan_paypal_prices.id')
            ->join('plans', 'plan_paypal_prices.plan_id', '=', 'plans.id')
            ->where('paypal_subscriptions.status', 'ACTIVE')
            ->where(fn ($query) => $query->where('plan_paypal_prices.unit_amount', 0)
                ->orWhere(fn ($paid) => $paid->whereNotNull('paypal_subscriptions.last_paid_at')
                    ->where('paypal_subscriptions.next_billing_at', '>', now())))
            ->select('plan_paypal_prices.interval', 'plan_paypal_prices.unit_amount',
                'plan_paypal_prices.currency', 'plan_paypal_prices.verified_at', 'plans.currency as plan_currency')
            ->selectRaw('COUNT(*) as aggregate_count')
            ->groupBy('plan_paypal_prices.interval', 'plan_paypal_prices.unit_amount',
                'plan_paypal_prices.currency', 'plan_paypal_prices.verified_at', 'plans.currency')
            ->get();
        foreach ($paypalPrices as $price) {
            $count = (int) $price->aggregate_count;
            if ($price->verified_at !== null && strtolower($price->currency) === $currency
                && strtolower($price->plan_currency) === $currency && in_array($price->interval, ['month', 'year'], true)) {
                $monthly = $price->interval === 'year' ? (int) round($price->unit_amount / 12) : (int) $price->unit_amount;
                $mrr += $monthly * $count;
                $mapped += $count;
            } else {
                $unmapped += $count;
            }
        }

        return [
            'states' => $states,
            'mrrCents' => $mrr,
            'arrCents' => $mrr * 12,
            'mappedSubscriptions' => $mapped,
            'unmappedSubscriptions' => $unmapped,
            'source' => 'Mapped Stripe and PayPal subscription prices; overrides are excluded from revenue.',
        ];
    }

    /**
     * @return list<array{date: string, users: int, workspaces: int, clicks: int, scans: int, events: int}>
     */
    private function platformDaily(AdminInsightsRange $range): array
    {
        $users = $this->dailyCounts('users', 'created_at', $range);
        $workspaces = $this->dailyCounts('workspaces', 'created_at', $range);
        $clicks = $this->dailyCounts('clicks', 'occurred_at', $range);
        $scans = $this->dailyCounts('qr_scans', 'scanned_at', $range);
        $events = $this->dailyCounts('analytics_events', 'occurred_at', $range);

        /** @var list<array{date: string, users: int, workspaces: int, clicks: int, scans: int, events: int}> $daily */
        $daily = $this->dailyRange($range, fn (string $date): array => [
            'date' => $date,
            'users' => $users[$date] ?? 0,
            'workspaces' => $workspaces[$date] ?? 0,
            'clicks' => $clicks[$date] ?? 0,
            'scans' => $scans[$date] ?? 0,
            'events' => $events[$date] ?? 0,
        ]);

        return $daily;
    }

    /**
     * @return list<array{date: string, events: int, uniqueVisitors: int, clicks: int, scans: int, pageViews: int}>
     */
    private function visitorDaily(AdminInsightsRange $range): array
    {
        $events = $this->dailyCounts('analytics_events', 'occurred_at', $range);
        $unique = $this->dailyCounts('analytics_events', 'occurred_at', $range, 'visitor_hash');
        $clicks = $this->dailyCounts('clicks', 'occurred_at', $range);
        $scans = $this->dailyCounts('qr_scans', 'scanned_at', $range);
        $pageViews = $this->dailyEventCounts($range, ['link_page.view', 'bio_page.view', 'overlay.view']);

        /** @var list<array{date: string, events: int, uniqueVisitors: int, clicks: int, scans: int, pageViews: int}> $daily */
        $daily = $this->dailyRange($range, fn (string $date): array => [
            'date' => $date,
            'events' => $events[$date] ?? 0,
            'uniqueVisitors' => $unique[$date] ?? 0,
            'clicks' => $clicks[$date] ?? 0,
            'scans' => $scans[$date] ?? 0,
            'pageViews' => $pageViews[$date] ?? 0,
        ]);

        return $daily;
    }

    /**
     * @param  callable(string): array<string, mixed>  $row
     * @return list<array<string, mixed>>
     */
    private function dailyRange(AdminInsightsRange $range, callable $row): array
    {
        $days = [];
        $date = $range->start;

        while ($date < $range->end) {
            $key = $date->toDateString();
            $days[] = $row($key);
            $date = $date->addDay();
        }

        return $days;
    }

    /**
     * @return array<string, int>
     */
    private function dailyCounts(string $table, string $column, AdminInsightsRange $range, ?string $distinct = null): array
    {
        $count = $distinct === null ? 'COUNT(*)' : "COUNT(DISTINCT {$distinct})";
        /** @var literal-string $dateExpression */
        $dateExpression = "DATE({$column})";
        /** @var literal-string $countExpression */
        $countExpression = "{$count} as aggregate_count";

        return DB::table($table)
            ->where($column, '>=', $range->start)
            ->where($column, '<', $range->end)
            ->select([
                DB::raw("{$dateExpression} as aggregate_date"),
                DB::raw($countExpression),
            ])
            ->groupBy(DB::raw($dateExpression))
            ->get()
            ->mapWithKeys(fn (object $row): array => [
                (string) $row->aggregate_date => (int) $row->aggregate_count,
            ])
            ->all();
    }

    /**
     * @param  list<string>  $eventNames
     * @return array<string, int>
     */
    private function dailyEventCounts(AdminInsightsRange $range, array $eventNames): array
    {
        return DB::table('analytics_events')
            ->whereIn('event_name', $eventNames)
            ->where('occurred_at', '>=', $range->start)
            ->where('occurred_at', '<', $range->end)
            ->selectRaw("DATE(occurred_at) as aggregate_date, COUNT(*) as aggregate_count")
            ->groupByRaw('DATE(occurred_at)')
            ->get()
            ->mapWithKeys(fn (object $row): array => [
                (string) $row->aggregate_date => (int) $row->aggregate_count,
            ])
            ->all();
    }

    /**
     * @return array<string, list<array{name: string, count: int}>>
     */
    private function dimensions(AdminInsightsRange $range): array
    {
        $events = $this->eventQuery($range);

        return [
            'countries' => $this->grouped(clone $events, 'country_code'),
            'browsers' => $this->grouped(clone $events, 'browser'),
            'platforms' => $this->grouped(clone $events, 'platform'),
            'devices' => $this->grouped(
                (clone $events)->whereNotNull('platform'),
                "CASE WHEN platform IN ('Android', 'iOS') THEN 'Mobile' WHEN platform IN ('Windows', 'macOS', 'Linux') THEN 'Desktop' ELSE 'Other device' END",
            ),
            'referrers' => $this->grouped(clone $events, 'referrer_host'),
        ];
    }

    /**
     * @param  Builder<AnalyticsEvent>  $events
     * @return list<array{name: string, count: int}>
     */
    private function grouped(Builder $events, string $expression): array
    {
        /** @var literal-string $bucketExpression */
        $bucketExpression = "{$expression} as bucket";
        /** @var literal-string $groupExpression */
        $groupExpression = $expression;
        $rows = $events
            ->select([DB::raw($bucketExpression)])
            ->selectRaw('COUNT(*) as aggregate_count')
            ->groupBy(DB::raw($groupExpression))
            ->get();

        $buckets = [];

        foreach ($rows as $row) {
            $name = trim((string) $row->getAttribute('bucket'));

            if ($name === '') {
                continue;
            }

            $count = (int) $row->getAttribute('aggregate_count');

            if ($count < self::K_ANONYMITY_THRESHOLD) {
                $buckets['Other'] = ($buckets['Other'] ?? 0) + $count;

                continue;
            }

            $buckets[$name] = ($buckets[$name] ?? 0) + $count;
        }

        arsort($buckets);
        $top = array_slice($buckets, 0, 6, true);
        $tail = array_sum(array_slice($buckets, 6, null, true));

        if ($tail > 0) {
            $top['Other'] = ($top['Other'] ?? 0) + $tail;
        }

        return array_values(collect($top)
            ->map(fn (int $count, string $name): array => ['name' => $name, 'count' => $count])
            ->all());
    }

    /**
     * @param  Builder<AnalyticsEvent>  $events
     * @return list<array{name: string, count: int}>
     */
    private function eventSeries(Builder $events): array
    {
        return array_values($events
            ->select('event_name')
            ->selectRaw('COUNT(*) as aggregate_count')
            ->groupBy('event_name')
            ->orderByDesc('aggregate_count')
            ->orderBy('event_name')
            ->limit(20)
            ->get()
            ->map(fn (AnalyticsEvent $event): array => [
                'name' => $event->event_name,
                'count' => (int) $event->getAttribute('aggregate_count'),
            ])
            ->all());
    }

    /**
     * @return Builder<AnalyticsEvent>
     */
    private function eventQuery(AdminInsightsRange $range): Builder
    {
        return $this->inRange(AnalyticsEvent::query(), 'occurred_at', $range);
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    private function inRange(Builder $query, string $column, AdminInsightsRange $range): Builder
    {
        return $query
            ->where($column, '>=', $range->start)
            ->where($column, '<', $range->end);
    }
}