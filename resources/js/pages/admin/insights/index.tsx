import { Head, Link } from '@inertiajs/react';
import { ExternalLink } from 'lucide-react';
import { ChartLegend } from '@/components/admin/insights/chart-legend';
import { DimensionPanel } from '@/components/admin/insights/dimension-panel';
import {
    InsightDailyBarChart,
    InsightDailySmallMultiples,
} from '@/components/admin/insights/daily-chart';
import { InsightsRangePicker } from '@/components/admin/insights/range-picker';
import { StatCard } from '@/components/dashboard/stat-card';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { countryName } from '@/lib/countries';
import insights from '@/routes/admin/insights';
import type {
    AdminDimension,
    AdminPlatformDaily,
    AdminPlatformInsightsPageProps,
} from '@/types/admin-insights';

const activitySeries = [
    { key: 'clicks', label: 'Clicks', color: 'var(--chart-1)' },
    { key: 'scans', label: 'QR scans', color: 'var(--chart-2)' },
    { key: 'events', label: 'Tracked events', color: 'var(--chart-5)' },
];

const growthSeries = [
    { key: 'users', label: 'New accounts', color: 'var(--chart-1)' },
    { key: 'workspaces', label: 'New workspaces', color: 'var(--chart-2)' },
];

const resourceLabels: Record<string, string> = {
    links: 'Short links',
    folders: 'Folders',
    qrCampaigns: 'QR campaigns',
    bioPages: 'Bio pages',
    domains: 'Custom domains',
    pixels: 'Tracking pixels',
    members: 'Members',
    webhooks: 'Webhooks',
    linkPages: 'Link pages',
    apiKeys: 'API keys',
    storageBytes: 'Retained media',
};

function subscriptionName(state: string): string {
    const label = state.replaceAll('_', ' ');

    return label.charAt(0).toUpperCase() + label.slice(1);
}

function money(cents: number): string {
    const amount = cents / 100;

    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: 'USD',
        maximumFractionDigits: amount % 1 === 0 ? 0 : 2,
    }).format(amount);
}

function number(value: number): string {
    return value.toLocaleString();
}

export default function AdminInsightsIndex({
    insights: data,
}: AdminPlatformInsightsPageProps) {
    const subscriptionRows: AdminDimension[] = data.subscriptions.states
        .map((state) => ({ name: state.state, count: state.count }))
        .sort((a, b) => b.count - a.count);

    const resolvedCountryEvents = data.dimensions.countries.reduce(
        (total, row) => total + row.count,
        0,
    );
    const unresolvedCountryEvents = Math.max(
        data.engagement.events - resolvedCountryEvents,
        0,
    );
    const countryFooter =
        unresolvedCountryEvents > 0
            ? `${unresolvedCountryEvents.toLocaleString()} of ${data.engagement.events.toLocaleString()} events have no resolved country.`
            : undefined;

    const activeSubscriptions = data.subscriptions.states
        .filter((state) => ['active', 'trialing'].includes(state.state))
        .reduce((total, state) => total + state.count, 0);
    const retainedMedia = `${Math.round(data.resources.storageBytes / 1024 / 1024).toLocaleString()} MB`;
    const snapshotRows: Array<[string, string]> = [
        ['Suspended', number(data.accounts.suspended)],
        ['Deletion pending', number(data.accounts.deletionPending)],
        ['Active subscriptions', number(activeSubscriptions)],
        [
            'Mapped subscriptions',
            number(data.subscriptions.mappedSubscriptions),
        ],
        [
            'Unmapped subscriptions',
            number(data.subscriptions.unmappedSubscriptions),
        ],
        ['Retained media', retainedMedia],
    ];
    const resourceRows = Object.entries(data.resources).map(([key, value]) => [
        resourceLabels[key] ?? key,
        key === 'storageBytes' ? retainedMedia : Number(value).toLocaleString(),
    ]);

    return (
        <>
            <Head title="Platform insights — Administration" />
            <div className="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-5 px-5 pt-4 pb-10">
                <PageHeader
                    title="Platform insights"
                    description="Accounts, workspaces, provider revenue, content totals, and consented activity."
                    actions={
                        <Button asChild variant="outline">
                            <Link href={insights.visitors().url}>
                                <ExternalLink aria-hidden="true" />
                                Visitor insights
                            </Link>
                        </Button>
                    }
                />

                <InsightsRangePicker
                    range={data.range}
                    url={insights.index().url}
                />

                <section
                    aria-label="Platform totals"
                    className="grid grid-cols-2 gap-3 xl:grid-cols-4"
                >
                    <StatCard
                        label="Accounts"
                        value={number(data.accounts.total)}
                        note={`${number(data.accounts.new)} new in window`}
                        sparkline={data.daily.map((day) => day.users)}
                        sparklineColor="var(--chart-2)"
                    />
                    <StatCard
                        label="Workspaces"
                        value={number(data.workspaces.total)}
                        note={`${number(data.workspaces.new)} new in window`}
                        sparkline={data.daily.map((day) => day.workspaces)}
                        sparklineColor="var(--chart-4)"
                    />
                    <StatCard
                        label="Provider MRR"
                        value={money(data.subscriptions.mrrCents)}
                        note="Current snapshot"
                    />
                    <StatCard
                        label="Tracked events"
                        value={number(data.engagement.events)}
                        note={`${number(data.engagement.clicks)} clicks · ${number(data.engagement.scans)} scans`}
                        sparkline={data.daily.map((day) => day.events)}
                        sparklineColor="var(--chart-3)"
                    />
                </section>

                <div className="flex flex-wrap gap-2">
                    {[
                        ['Suspended', number(data.accounts.suspended)],
                        [
                            'Deletion pending',
                            number(data.accounts.deletionPending),
                        ],
                        ['Active subscriptions', number(activeSubscriptions)],
                        [
                            'Mapped subscriptions',
                            number(data.subscriptions.mappedSubscriptions),
                        ],
                        [
                            'Unmapped subscriptions',
                            number(data.subscriptions.unmappedSubscriptions),
                        ],
                        [
                            'Retained media',
                            `${Math.round(data.resources.storageBytes / 1024 / 1024).toLocaleString()} MB`,
                        ],
                    ].map(([label, value]) => (
                        <Badge
                            key={label}
                            variant="secondary"
                            className="gap-1.5 px-2.5 py-1 font-normal"
                        >
                            <span className="text-muted-foreground">
                                {label}
                            </span>
                            <span className="font-medium text-foreground tabular-nums">
                                {value}
                            </span>
                        </Badge>
                    ))}
                </div>

                <section className="grid items-start gap-4 xl:grid-cols-2">
                    <Card className="min-w-0">
                        <CardHeader className="gap-1">
                            <CardTitle as="h2">Daily activity</CardTitle>
                            <CardDescription>
                                Clicks, QR scans, and consented tracked events
                                per UTC day.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid min-w-0 gap-4">
                            <InsightDailySmallMultiples
                                data={data.daily}
                                series={activitySeries}
                                values={{
                                    clicks: data.engagement.clicks,
                                    scans: data.engagement.scans,
                                    events: data.engagement.events,
                                }}
                                label="Daily clicks, QR scans, and tracked events per UTC day. Exact values are listed below."
                            />
                            <ExactPlatformTable data={data.daily} />
                        </CardContent>
                    </Card>

                    <Card className="min-w-0">
                        <CardHeader className="gap-1">
                            <CardTitle as="h2">Daily growth</CardTitle>
                            <CardDescription>
                                New accounts and workspaces created per UTC day.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid min-w-0 gap-4">
                            <ChartLegend
                                series={growthSeries}
                                values={{
                                    users: data.accounts.new,
                                    workspaces: data.workspaces.new,
                                }}
                            />
                            <InsightDailyBarChart
                                data={data.daily}
                                series={growthSeries}
                                label="Daily new accounts and workspaces. Exact values are listed below."
                            />
                            <ExactPlatformTable data={data.daily} growthOnly />
                        </CardContent>
                    </Card>
                </section>

                <section className="grid items-start gap-4 xl:grid-cols-2">
                    <DimensionPanel
                        title="Cashier subscription states"
                        description="Current stored states. Grace-period enterprise flows remain provider-owned."
                        rows={subscriptionRows}
                        visual="donut"
                        nameFormatter={subscriptionName}
                        totalLabel="subscriptions"
                        emptyMessage="No provider subscriptions are stored."
                        cardVariant={
                            subscriptionRows.length === 0
                                ? 'transparent'
                                : 'default'
                        }
                    />

                    <Card className="min-w-0">
                        <CardHeader className="gap-1">
                            <CardTitle as="h2">Account health</CardTitle>
                            <CardDescription>
                                Snapshot values that do not change with the
                                selected window.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid grid-cols-2 gap-x-6 gap-y-4">
                            {snapshotRows.map(([label, value]) => (
                                <div key={label} className="grid gap-0.5">
                                    <p className="text-xs text-muted-foreground">
                                        {label}
                                    </p>
                                    <p className="text-sm font-medium tabular-nums">
                                        {value}
                                    </p>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                </section>

                <section className="grid gap-3">
                    <div className="flex flex-wrap items-end justify-between gap-2">
                        <div className="grid gap-1">
                            <div className="flex items-center gap-2">
                                <h2 className="text-base leading-6 font-semibold tracking-[-0.01em]">
                                    Resource totals
                                </h2>
                                <Badge
                                    variant="secondary"
                                    className="font-normal"
                                >
                                    {resourceRows.length}
                                </Badge>
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Current totals only, not scoped to the selected
                                window. Destinations and per-workspace
                                drill-down are excluded.
                            </p>
                        </div>
                    </div>
                    <div className="lp-table-shell overflow-x-auto">
                        <table
                            data-table-surface="populated"
                            className="w-full border-collapse text-sm"
                            aria-label="Resource totals"
                        >
                            <thead className="bg-surface-secondary text-left text-xs text-muted-foreground">
                                <tr>
                                    <th className="px-4 py-3 font-medium">
                                        Resource
                                    </th>
                                    <th className="px-4 py-3 text-right font-medium">
                                        Total
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {resourceRows.map(([label, value]) => (
                                    <tr
                                        key={label}
                                        className="border-t border-separator"
                                    >
                                        <td className="px-4 py-3">{label}</td>
                                        <td className="px-4 py-3 text-right font-medium tabular-nums">
                                            {value}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>

                <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <DimensionPanel
                        title="Countries"
                        description="Country buckets from consented events. Shares cover events with a resolved country."
                        rows={data.dimensions.countries}
                        threshold={data.kAnonymityThreshold}
                        nameFormatter={countryName}
                        footer={countryFooter}
                    />
                    <DimensionPanel
                        title="Browsers"
                        description="Coarse browser families from consented events."
                        rows={data.dimensions.browsers}
                        threshold={data.kAnonymityThreshold}
                    />
                    <DimensionPanel
                        title="Platforms"
                        description="Operating-system families from consented events."
                        rows={data.dimensions.platforms}
                        threshold={data.kAnonymityThreshold}
                    />
                    <DimensionPanel
                        title="Device classes"
                        description="Mobile, desktop, or other coarse classes."
                        rows={data.dimensions.devices}
                        threshold={data.kAnonymityThreshold}
                        visual="donut"
                        totalLabel="events"
                    />
                    <DimensionPanel
                        title="Referrers"
                        description="Referrer hostnames only; full URLs are never retained."
                        rows={data.dimensions.referrers}
                        threshold={data.kAnonymityThreshold}
                    />
                    <div className="lp-panel grid gap-2 p-5 text-sm text-muted-foreground">
                        <h2 className="font-semibold text-foreground">
                            Privacy boundary
                        </h2>
                        <p>
                            Raw visitor hashes, IP addresses, cities,
                            destinations, and tenant drill-down are never
                            returned. Dimension buckets under{' '}
                            {data.kAnonymityThreshold} events collapse into
                            Other. Dimension shares cover events with a recorded
                            value for that dimension.
                        </p>
                    </div>
                </section>
            </div>
        </>
    );
}

function ExactPlatformTable({
    data,
    growthOnly = false,
}: {
    data: AdminPlatformDaily[];
    growthOnly?: boolean;
}) {
    return (
        <details>
            <summary className="cursor-pointer text-sm text-muted-foreground">
                Exact daily values
            </summary>
            <div className="mt-2 max-h-64 overflow-auto rounded-md border">
                <table
                    data-table-surface={
                        data.length > 0 ? 'populated' : undefined
                    }
                    className="w-full text-xs"
                >
                    <thead className="sticky top-0 bg-muted text-left text-xs text-muted-foreground">
                        <tr>
                            <th className="px-2 py-1 font-medium">Date</th>
                            {growthOnly ? (
                                <>
                                    <th className="px-2 py-1 text-right font-medium">
                                        Accounts
                                    </th>
                                    <th className="px-2 py-1 text-right font-medium">
                                        Workspaces
                                    </th>
                                </>
                            ) : (
                                <>
                                    <th className="px-2 py-1 text-right font-medium">
                                        Clicks
                                    </th>
                                    <th className="px-2 py-1 text-right font-medium">
                                        Scans
                                    </th>
                                    <th className="px-2 py-1 text-right font-medium">
                                        Events
                                    </th>
                                </>
                            )}
                        </tr>
                    </thead>
                    <tbody>
                        {data.map((row) => (
                            <tr key={row.date} className="border-t">
                                <td className="px-2 py-1">{row.date}</td>
                                {growthOnly ? (
                                    <>
                                        <td className="px-2 py-1 text-right tabular-nums">
                                            {row.users}
                                        </td>
                                        <td className="px-2 py-1 text-right tabular-nums">
                                            {row.workspaces}
                                        </td>
                                    </>
                                ) : (
                                    <>
                                        <td className="px-2 py-1 text-right tabular-nums">
                                            {row.clicks}
                                        </td>
                                        <td className="px-2 py-1 text-right tabular-nums">
                                            {row.scans}
                                        </td>
                                        <td className="px-2 py-1 text-right tabular-nums">
                                            {row.events}
                                        </td>
                                    </>
                                )}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </details>
    );
}
