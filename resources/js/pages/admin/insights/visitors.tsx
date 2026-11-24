import { Head, Link } from '@inertiajs/react';
import {
    Activity,
    ArrowLeft,
    Compass,
    Eye,
    Laptop,
    MonitorSmartphone,
    MousePointerClick,
    ScanLine,
    Share2,
    ShieldCheck,
    UsersRound,
} from 'lucide-react';
import { InsightActivityCard } from '@/components/admin/insights/activity-card';
import { DimensionPanel } from '@/components/admin/insights/dimension-panel';
import { InsightsRangePicker } from '@/components/admin/insights/range-picker';
import { RankedBreakdownCard } from '@/components/analytics/ranked-breakdown-card';
import { TopCountriesCard } from '@/components/analytics/traffic-sources';
import { StatCard } from '@/components/dashboard/stat-card';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import insights from '@/routes/admin/insights';
import type {
    AdminDimension,
    AdminVisitorInsightsPageProps,
} from '@/types/admin-insights';

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
            <div className="mx-auto flex w-full max-w-7xl min-w-0 flex-1 flex-col gap-5 px-5 pt-4 pb-10">
                <div className="flex flex-col gap-3">
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
                    <PageHeader
                        title="Visitor insights"
                        description="Aggregate consented events only. No workspace drill-down, per-link ranking, raw hash, IP address, city, destination, or full referrer URL."
                        actions={
                            <InsightsRangePicker
                                range={data.range}
                                url={insights.visitors().url}
                                variant="inline"
                            />
                        }
                    />
                </div>

                <section
                    aria-label="Visitor totals"
                    className="grid grid-cols-2 gap-3 lg:grid-cols-5"
                >
                    <StatCard
                        icon={Activity}
                        label="Tracked events"
                        value={number(data.totals.events)}
                        note={`${data.range.days} days`}
                    />
                    <StatCard
                        icon={UsersRound}
                        label="Unique visitors"
                        value={number(data.totals.uniqueVisitors)}
                        note={`${data.range.days} days`}
                    />
                    <StatCard
                        icon={Eye}
                        label="Page views"
                        value={number(data.totals.pageViews)}
                        note={`${data.range.days} days`}
                    />
                    <StatCard
                        icon={MousePointerClick}
                        label="Clicks"
                        value={number(data.totals.clicks)}
                        note={`${data.range.days} days`}
                    />
                    <StatCard
                        icon={ScanLine}
                        label="QR scans"
                        value={number(data.totals.scans)}
                        note={`${data.range.days} days`}
                        className="max-lg:col-span-2"
                    />
                </section>

                <InsightActivityCard
                    title="Daily aggregate series"
                    description="Tracked events, unique visitors, and page views per UTC day."
                    daily={data.daily}
                    days={data.range.days}
                    defaultMetric="events"
                    options={[
                        {
                            value: 'events',
                            label: 'Tracked events',
                            shortLabel: 'Events',
                            total: data.totals.events,
                        },
                        {
                            value: 'uniqueVisitors',
                            label: 'Unique visitors',
                            shortLabel: 'Visitors',
                            total: data.totals.uniqueVisitors,
                        },
                        {
                            value: 'pageViews',
                            label: 'Page views',
                            shortLabel: 'Views',
                            total: data.totals.pageViews,
                        },
                    ]}
                />

                <TopCountriesCard
                    rows={data.dimensions.countries}
                    metric="events"
                    footer={countryFooter}
                />

                <section aria-label="Audience breakdown" className="grid gap-3">
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <DimensionPanel
                            title="Top browsers"
                            description="Consented events by browser family."
                            rows={data.dimensions.browsers}
                            visual="donut"
                            totalLabel="events"
                            icon={Compass}
                        />
                        <DimensionPanel
                            title="Top platforms"
                            description="Consented events by operating system."
                            rows={data.dimensions.platforms}
                            visual="donut"
                            totalLabel="events"
                            icon={MonitorSmartphone}
                        />
                        <DimensionPanel
                            title="Device classes"
                            description="Consented events by coarse device class."
                            rows={data.dimensions.devices}
                            visual="donut"
                            totalLabel="events"
                            icon={Laptop}
                        />
                    </div>
                    <p className="text-xs text-muted-foreground">
                        Dimension buckets under {data.kAnonymityThreshold}{' '}
                        events are grouped as Other. Shares cover events with a
                        recorded value for that dimension.
                    </p>
                </section>

                <section className="grid items-start gap-4 lg:grid-cols-2">
                    <RankedBreakdownCard
                        title="Event series"
                        description="Top event names by volume. The remainder is grouped as Other events."
                        icon={Activity}
                        rows={eventRows}
                        emptyTitle="No consenting events in this window"
                        emptyDescription="Choose another window or check again after consented activity arrives."
                    />
                    <RankedBreakdownCard
                        title="Top referrers"
                        description="Referrer hostnames only. Full URLs are never retained."
                        icon={Share2}
                        rows={data.dimensions.referrers}
                        emptyTitle="No referrer data in this window"
                        emptyDescription="A referrer appears only when the browser sends one."
                    />
                </section>

                <div className="flex items-start gap-3 rounded-2xl border border-border bg-card p-4 shadow-[var(--shadow-surface)]">
                    <ShieldCheck
                        className="mt-0.5 size-4 shrink-0 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <p className="max-w-5xl text-xs leading-5 text-muted-foreground">
                        Daily unique visitors are counted per day from keyed
                        hashes and only aggregate counts leave the server. Raw
                        visitor hashes, IP addresses, cities, destinations,
                        link-level rankings, and workspace drill-down are never
                        returned.
                    </p>
                </div>
            </div>
        </>
    );
}
