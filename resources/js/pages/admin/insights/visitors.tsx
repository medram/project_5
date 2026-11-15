import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, ShieldCheck } from 'lucide-react';
import { ChartLegend } from '@/components/admin/insights/chart-legend';
import { DimensionPanel } from '@/components/admin/insights/dimension-panel';
import { InsightDailyChart } from '@/components/admin/insights/daily-chart';
import { InsightsRangePicker } from '@/components/admin/insights/range-picker';
import { StatCard } from '@/components/dashboard/stat-card';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { countryName } from '@/lib/countries';
import insights from '@/routes/admin/insights';
import type {
    AdminDimension,
    AdminVisitorDaily,
    AdminVisitorInsightsPageProps,
} from '@/types/admin-insights';

const visitorSeries = [
    { key: 'events', label: 'Tracked events', color: 'var(--chart-1)' },
    {
        key: 'uniqueVisitors',
        label: 'Aggregate unique visitors',
        color: 'var(--chart-2)',
    },
    { key: 'pageViews', label: 'Page views', color: 'var(--chart-3)' },
];

function collapseEventSeries(rows: AdminDimension[]): AdminDimension[] {
    const visible = rows.slice(0, 8);
    const hidden = rows.slice(8);

    if (hidden.length === 0) {
        return visible;
    }

    return [
        ...visible,
        {
            name: 'Other events',
            count: hidden.reduce((total, row) => total + row.count, 0),
        },
    ];
}

function number(value: number): string {
    return value.toLocaleString();
}

export default function AdminVisitorInsights({
    insights: data,
}: AdminVisitorInsightsPageProps) {
    const resolvedCountryEvents = data.dimensions.countries.reduce(
        (total, row) => total + row.count,
        0,
    );
    const unresolvedCountryEvents = Math.max(
        data.totals.events - resolvedCountryEvents,
        0,
    );
    const countryFooter =
        unresolvedCountryEvents > 0
            ? `${unresolvedCountryEvents.toLocaleString()} of ${data.totals.events.toLocaleString()} events have no resolved country.`
            : undefined;
    const eventRows = collapseEventSeries(data.eventSeries);

    return (
        <>
            <Head title="Visitor insights — Administration" />
            <div className="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-5 px-5 pt-4 pb-10">
                <header className="flex flex-col gap-3">
                    <Button
                        asChild
                        variant="ghost"
                        size="sm"
                        className="self-start px-0"
                    >
                        <Link href={insights.index().url}>
                            <ArrowLeft aria-hidden="true" />
                            Platform insights
                        </Link>
                    </Button>
                    <div className="space-y-1">
                        <h1 className="text-2xl leading-8 font-semibold tracking-[-0.02em]">
                            Visitor insights
                        </h1>
                        <p className="max-w-3xl text-sm text-muted-foreground">
                            Aggregate consented events only. There is no
                            workspace drill-down, per-link ranking, raw hash, IP
                            address, city, destination, or full referrer URL.
                        </p>
                    </div>
                </header>

                <InsightsRangePicker
                    range={data.range}
                    url={insights.visitors().url}
                />

                <section
                    aria-label="Visitor totals"
                    className="grid grid-cols-2 gap-3 xl:grid-cols-5"
                >
                    <StatCard
                        label="Tracked events"
                        value={number(data.totals.events)}
                        sparkline={data.daily.map((day) => day.events)}
                        sparklineColor="var(--chart-2)"
                    />
                    <StatCard
                        label="Unique visitors"
                        value={number(data.totals.uniqueVisitors)}
                        sparkline={data.daily.map((day) => day.uniqueVisitors)}
                        sparklineColor="var(--chart-4)"
                    />
                    <StatCard
                        label="Page views"
                        value={number(data.totals.pageViews)}
                    />
                    <StatCard
                        label="Clicks"
                        value={number(data.totals.clicks)}
                    />
                    <StatCard
                        label="QR scans"
                        value={number(data.totals.scans)}
                    />
                </section>

                <Card className="min-w-0">
                    <CardHeader className="gap-1">
                        <CardTitle as="h2">Daily aggregate series</CardTitle>
                        <CardDescription>
                            Unique visitors are counted per day from keyed
                            hashes and only the aggregate count leaves the
                            server.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid min-w-0 gap-4">
                        <ChartLegend
                            series={visitorSeries}
                            values={{
                                events: data.totals.events,
                                uniqueVisitors: data.totals.uniqueVisitors,
                                pageViews: data.totals.pageViews,
                            }}
                        />
                        <InsightDailyChart
                            data={data.daily}
                            series={visitorSeries}
                            label="Daily tracked events, aggregate unique visitors, and page views. Exact values are listed below."
                        />
                        <details>
                            <summary className="cursor-pointer text-sm text-muted-foreground">
                                Exact daily values
                            </summary>
                            <ExactVisitorTable data={data.daily} />
                        </details>
                    </CardContent>
                </Card>

                <section className="grid gap-4 xl:grid-cols-[minmax(0,1.2fr)_minmax(0,0.8fr)]">
                    <DimensionPanel
                        title="Event series"
                        description="Top event names by volume. The remainder is grouped as Other events; no workspace, resource, or destination identifiers."
                        rows={eventRows}
                        emptyMessage="No consenting events in this window."
                        cardVariant={
                            eventRows.length === 0 ? 'transparent' : 'default'
                        }
                    />

                    <Card>
                        <CardHeader className="gap-1">
                            <CardTitle
                                className="flex items-center gap-2"
                                as="h2"
                            >
                                <ShieldCheck
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                Privacy boundary
                            </CardTitle>
                            <CardDescription>
                                Every dimension below is coarse and collapsed
                                before serialization.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-3 text-sm text-muted-foreground">
                            <p>
                                Buckets with fewer than{' '}
                                {data.kAnonymityThreshold} events become Other.
                            </p>
                            <p>
                                Dimension shares cover events with a recorded
                                value for that dimension.
                            </p>
                            <Separator />
                            <p>
                                Raw visitor hashes, IP addresses, cities,
                                destinations, link-level rankings, and
                                per-workspace dimensions are never returned.
                            </p>
                            <p>
                                This page is an aggregate operational view, not
                                a customer-level analytics export.
                            </p>
                        </CardContent>
                    </Card>
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
                </section>
            </div>
        </>
    );
}

function ExactVisitorTable({ data }: { data: AdminVisitorDaily[] }) {
    return (
        <div className="mt-2 max-h-64 overflow-auto rounded-md border">
            <table
                data-table-surface={data.length > 0 ? 'populated' : undefined}
                className="w-full text-xs"
            >
                <thead className="sticky top-0 bg-muted text-left text-xs text-muted-foreground">
                    <tr>
                        <th className="px-2 py-1 font-medium">Date</th>
                        <th className="px-2 py-1 text-right font-medium">
                            Events
                        </th>
                        <th className="px-2 py-1 text-right font-medium">
                            Unique
                        </th>
                        <th className="px-2 py-1 text-right font-medium">
                            Page views
                        </th>
                        <th className="px-2 py-1 text-right font-medium">
                            Clicks
                        </th>
                        <th className="px-2 py-1 text-right font-medium">
                            Scans
                        </th>
                    </tr>
                </thead>
                <tbody>
                    {data.map((row) => (
                        <tr key={row.date} className="border-t">
                            <td className="px-2 py-1">{row.date}</td>
                            <td className="px-2 py-1 text-right tabular-nums">
                                {row.events}
                            </td>
                            <td className="px-2 py-1 text-right tabular-nums">
                                {row.uniqueVisitors}
                            </td>
                            <td className="px-2 py-1 text-right tabular-nums">
                                {row.pageViews}
                            </td>
                            <td className="px-2 py-1 text-right tabular-nums">
                                {row.clicks}
                            </td>
                            <td className="px-2 py-1 text-right tabular-nums">
                                {row.scans}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
