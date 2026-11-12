import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, BarChart3, ShieldCheck } from 'lucide-react';
import { DimensionPanel } from '@/components/admin/insights/dimension-panel';
import { InsightDailyChart } from '@/components/admin/insights/daily-chart';
import { InsightsRangePicker } from '@/components/admin/insights/range-picker';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import insights from '@/routes/admin/insights';
import type {
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

function number(value: number): string {
    return value.toLocaleString();
}

export default function AdminVisitorInsights({
    insights: data,
}: AdminVisitorInsightsPageProps) {
    return (
        <>
            <Head title="Visitor insights — Administration" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
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
                        <div className="flex items-center gap-2 text-sm text-muted-foreground">
                            <BarChart3 className="size-4" aria-hidden="true" />
                            <span>Privacy-safe visitor totals</span>
                        </div>
                        <h1 className="text-2xl font-semibold tracking-tight">
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

                <section className="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-5">
                    <VisitorStat
                        label="Tracked events"
                        value={number(data.totals.events)}
                    />
                    <VisitorStat
                        label="Unique visitors"
                        value={number(data.totals.uniqueVisitors)}
                    />
                    <VisitorStat
                        label="Page views"
                        value={number(data.totals.pageViews)}
                    />
                    <VisitorStat
                        label="Clicks"
                        value={number(data.totals.clicks)}
                    />
                    <VisitorStat
                        label="QR scans"
                        value={number(data.totals.scans)}
                    />
                </section>

                <Card className="min-w-0">
                    <CardHeader className="gap-1">
                        <CardTitle>Daily aggregate series</CardTitle>
                        <CardDescription>
                            Unique visitors are counted per day from keyed
                            hashes and only the aggregate count leaves the
                            server.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid min-w-0 gap-4">
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

                <section className="grid gap-4 xl:grid-cols-[minmax(0,0.8fr)_minmax(0,1.2fr)]">
                    <Card>
                        <CardHeader className="gap-1">
                            <CardTitle>Event series</CardTitle>
                            <CardDescription>
                                Aggregate event names only; no workspace,
                                resource, or destination identifiers.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {data.eventSeries.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    No consenting events in this window.
                                </p>
                            ) : (
                                <table className="w-full text-sm">
                                    <thead className="text-left text-xs text-muted-foreground uppercase">
                                        <tr>
                                            <th className="py-2 font-medium">
                                                Event
                                            </th>
                                            <th className="py-2 text-right font-medium">
                                                Count
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {data.eventSeries.map((event) => (
                                            <tr
                                                key={event.name}
                                                className="border-t"
                                            >
                                                <td className="py-2">
                                                    {event.name}
                                                </td>
                                                <td className="py-2 text-right tabular-nums">
                                                    {event.count.toLocaleString()}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="gap-1">
                            <CardTitle className="flex items-center gap-2">
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
                        description="Coarse ISO country buckets from consented events."
                        rows={data.dimensions.countries}
                        threshold={data.kAnonymityThreshold}
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

function VisitorStat({ label, value }: { label: string; value: string }) {
    return (
        <Card>
            <CardHeader className="gap-1">
                <CardDescription>{label}</CardDescription>
                <CardTitle className="text-2xl tabular-nums">{value}</CardTitle>
            </CardHeader>
        </Card>
    );
}

function ExactVisitorTable({ data }: { data: AdminVisitorDaily[] }) {
    return (
        <div className="mt-2 max-h-64 overflow-auto rounded-md border">
            <table className="w-full text-xs">
                <thead className="sticky top-0 bg-muted text-left uppercase">
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
