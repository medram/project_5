import { Head, Link } from '@inertiajs/react';
import {
    Activity,
    Building2,
    Compass,
    CreditCard,
    ExternalLink,
    Laptop,
    MonitorSmartphone,
    Share2,
    ShieldCheck,
    UsersRound,
} from 'lucide-react';
import { AccountHealthCard } from '@/components/admin/insights/account-health-card';
import { InsightActivityCard } from '@/components/admin/insights/activity-card';
import { ChartLegend } from '@/components/admin/insights/chart-legend';
import {
    InsightDailyBarChart,
    type InsightSeries,
} from '@/components/admin/insights/daily-chart';
import { DimensionPanel } from '@/components/admin/insights/dimension-panel';
import { InsightsRangePicker } from '@/components/admin/insights/range-picker';
import { ResourceTotalsCard } from '@/components/admin/insights/resource-totals-card';
import { RankedBreakdownCard } from '@/components/analytics/ranked-breakdown-card';
import { TopCountriesCard } from '@/components/analytics/traffic-sources';
import { StatCard } from '@/components/dashboard/stat-card';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import insights from '@/routes/admin/insights';
import type {
    AdminDimension,
    AdminPlatformInsightsPageProps,
} from '@/types/admin-insights';

const growthSeries: InsightSeries[] = [
    { key: 'users', label: 'New accounts', color: 'var(--chart-2)' },
    { key: 'workspaces', label: 'New workspaces', color: 'var(--chart-4)' },
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
    const resourceRows = Object.entries(data.resources).map(([key, value]) => [
        resourceLabels[key] ?? key,
        key === 'storageBytes' ? retainedMedia : Number(value).toLocaleString(),
    ]) as Array<[string, string]>;

    return (
        <>
            <Head title="Platform insights — Administration" />
            <div className="mx-auto flex w-full max-w-7xl min-w-0 flex-1 flex-col gap-5 px-5 pt-4 pb-10">
                <PageHeader
                    title="Platform insights"
                    description="Platform-wide accounts, revenue, resources, and consented activity."
                    actions={
                        <div className="flex flex-wrap items-center justify-end gap-2">
                            <InsightsRangePicker
                                range={data.range}
                                url={insights.index().url}
                                variant="inline"
                            />
                            <Button asChild variant="outline">
                                <Link href={insights.visitors().url}>
                                    <ExternalLink aria-hidden="true" />
                                    Visitor insights
                                </Link>
                            </Button>
                        </div>
                    }
                />

                <section
                    aria-label="Platform totals"
                    className="grid grid-cols-2 gap-3 lg:grid-cols-4"
                >
                    <StatCard
                        icon={UsersRound}
                        label="Accounts"
                        value={number(data.accounts.total)}
                        note={`${number(data.accounts.new)} new in window`}
                    />
                    <StatCard
                        icon={Building2}
                        label="Workspaces"
                        value={number(data.workspaces.total)}
                        note={`${number(data.workspaces.new)} new in window`}
                    />
                    <StatCard
                        icon={CreditCard}
                        label="Provider MRR"
                        value={money(data.subscriptions.mrrCents)}
                        note="Current snapshot"
                    />
                    <StatCard
                        icon={Activity}
                        label="Tracked events"
                        value={number(data.engagement.events)}
                        note={`${number(data.engagement.clicks)} clicks · ${number(data.engagement.scans)} scans`}
                    />
                </section>

                <InsightActivityCard
                    daily={data.daily}
                    description="Clicks, QR scans, and consented events per UTC day."
                    days={data.range.days}
                    defaultMetric="events"
                    options={[
                        {
                            value: 'clicks',
                            label: 'Clicks',
                            total: data.engagement.clicks,
                        },
                        {
                            value: 'scans',
                            label: 'QR scans',
                            total: data.engagement.scans,
                            emptyLabel: 'QR scans',
                        },
                        {
                            value: 'events',
                            label: 'Tracked events',
                            total: data.engagement.events,
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

                <section className="grid items-start gap-4 lg:grid-cols-[minmax(0,1.35fr)_minmax(0,0.65fr)]">
                    <RankedBreakdownCard
                        title="Top referrers"
                        description="Referrer hostnames from consented events. Full URLs are never retained."
                        icon={Share2}
                        rows={data.dimensions.referrers}
                        emptyTitle="No referrer data in this window"
                        emptyDescription="A referrer appears only when the browser sends one."
                    />
                    <ResourceTotalsCard rows={resourceRows} />
                </section>

                <section
                    aria-label="Growth and account health"
                    className="grid items-start gap-4 md:grid-cols-2 xl:grid-cols-[minmax(0,1.1fr)_minmax(0,1fr)_minmax(0,0.85fr)]"
                >
                    <Card className="min-w-0">
                        <CardHeader className="gap-1">
                            <CardTitle as="h2">Daily growth</CardTitle>
                            <CardDescription>
                                New accounts and workspaces per UTC day.
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
                                height={200}
                                label="New accounts and workspaces per UTC day."
                            />
                        </CardContent>
                    </Card>

                    <AccountHealthCard
                        accounts={data.accounts}
                        subscriptions={data.subscriptions}
                        activeSubscriptions={activeSubscriptions}
                    />

                    <div className="md:col-span-2 xl:col-span-1">
                        <DimensionPanel
                            title="Cashier subscription states"
                            description="Stored provider states. Grace-period enterprise flows remain provider-owned."
                            rows={subscriptionRows}
                            visual="donut"
                            nameFormatter={subscriptionName}
                            totalLabel="subscriptions"
                            emptyMessage="No provider subscriptions are stored."
                            icon={CreditCard}
                        />
                    </div>
                </section>

                <div className="flex items-start gap-3 rounded-2xl border border-border bg-card p-4 shadow-[var(--shadow-surface)]">
                    <ShieldCheck
                        className="mt-0.5 size-4 shrink-0 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <p className="max-w-5xl text-xs leading-5 text-muted-foreground">
                        Raw visitor hashes, IP addresses, cities, destinations,
                        and workspace drill-down are never returned. Dimension
                        shares cover events with a recorded value.
                    </p>
                </div>
            </div>
        </>
    );
}
