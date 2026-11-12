import { Head, Link } from '@inertiajs/react';
import { BarChart3, ExternalLink, ShieldCheck } from 'lucide-react';
import { DimensionPanel } from '@/components/admin/insights/dimension-panel';
import { InsightDailyChart } from '@/components/admin/insights/daily-chart';
import { InsightsRangePicker } from '@/components/admin/insights/range-picker';
import { Badge } from '@/components/ui/badge';
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
    AdminPlatformInsightsPageProps,
    AdminPlatformDaily,
} from '@/types/admin-insights';

const activitySeries = [
    { key: 'clicks', label: 'Clicks', color: 'var(--chart-1)' },
    { key: 'scans', label: 'QR scans', color: 'var(--chart-2)' },
    { key: 'events', label: 'Tracked events', color: 'var(--chart-3)' },
];

const growthSeries = [
    { key: 'users', label: 'New accounts', color: 'var(--chart-1)' },
    { key: 'workspaces', label: 'New workspaces', color: 'var(--chart-2)' },
];

function money(cents: number): string {
    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: 'USD',
    }).format(cents / 100);
}

function number(value: number): string {
    return value.toLocaleString();
}

export default function AdminInsightsIndex({
    insights: data,
}: AdminPlatformInsightsPageProps) {
    return (
        <>
            <Head title="Platform insights — Administration" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <header className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div className="space-y-1">
                        <div className="flex items-center gap-2 text-sm text-muted-foreground">
                            <BarChart3 className="size-4" aria-hidden="true" />
                            <span>Platform administration</span>
                        </div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Platform insights
                        </h1>
                        <p className="max-w-3xl text-sm text-muted-foreground">
                            Aggregate account, workspace, Cashier, resource, and
                            consented analytics totals. Provider MRR uses mapped
                            Stripe prices and never treats manual overrides as
                            revenue.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button asChild variant="outline" className="min-h-11">
                            <Link href={insights.visitors().url}>
                                <ExternalLink aria-hidden="true" />
                                Visitor insights
                            </Link>
                        </Button>
                    </div>
                </header>

                <InsightsRangePicker
                    range={data.range}
                    url={insights.index().url}
                />

                <section className="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                    <Stat label="Accounts" value={number(data.accounts.total)}>
                        {data.accounts.new.toLocaleString()} new ·{' '}
                        {data.accounts.active.toLocaleString()} active
                    </Stat>
                    <Stat
                        label="Workspaces"
                        value={number(data.workspaces.total)}
                    >
                        {data.workspaces.new.toLocaleString()} new
                    </Stat>
                    <Stat
                        label="Provider MRR"
                        value={money(data.subscriptions.mrrCents)}
                    >
                        {data.subscriptions.mappedSubscriptions} mapped ·{' '}
                        {data.subscriptions.unmappedSubscriptions} unmapped
                    </Stat>
                    <Stat
                        label="Provider ARR"
                        value={money(data.subscriptions.arrCents)}
                    >
                        {data.subscriptions.source}
                    </Stat>
                    <Stat
                        label="Tracked events"
                        value={number(data.engagement.events)}
                    >
                        {number(data.engagement.clicks)} clicks ·{' '}
                        {number(data.engagement.scans)} scans
                    </Stat>
                </section>

                <section className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <Stat
                        label="Suspended"
                        value={number(data.accounts.suspended)}
                    >
                        Current status
                    </Stat>
                    <Stat
                        label="Deletion pending"
                        value={number(data.accounts.deletionPending)}
                    >
                        Restorable 30-day window
                    </Stat>
                    <Stat
                        label="Active provider subscriptions"
                        value={number(
                            data.subscriptions.states
                                .filter((state) =>
                                    ['active', 'trialing'].includes(
                                        state.state,
                                    ),
                                )
                                .reduce(
                                    (total, state) => total + state.count,
                                    0,
                                ),
                        )}
                    >
                        Cashier states below
                    </Stat>
                    <Stat
                        label="Retained media"
                        value={`${Math.round(data.resources.storageBytes / 1024 / 1024).toLocaleString()} MB`}
                    >
                        {number(data.resources.apiKeys)} API keys
                    </Stat>
                </section>

                <section className="grid gap-4 xl:grid-cols-2">
                    <Card className="min-w-0">
                        <CardHeader className="gap-1">
                            <CardTitle>Daily activity</CardTitle>
                            <CardDescription>
                                Clicks, QR scans, and consented tracked events
                                in the selected UTC window.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid min-w-0 gap-4">
                            <InsightDailyChart
                                data={data.daily}
                                series={activitySeries}
                                label="Daily clicks, QR scans, and tracked events. Exact values are listed below."
                            />
                            <ExactPlatformTable data={data.daily} />
                        </CardContent>
                    </Card>

                    <Card className="min-w-0">
                        <CardHeader className="gap-1">
                            <CardTitle>Daily growth</CardTitle>
                            <CardDescription>
                                New accounts and workspaces created in the
                                selected UTC window.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid min-w-0 gap-4">
                            <InsightDailyChart
                                data={data.daily}
                                series={growthSeries}
                                label="Daily new accounts and workspaces. Exact values are listed below."
                            />
                            <ExactPlatformTable data={data.daily} growthOnly />
                        </CardContent>
                    </Card>
                </section>

                <section className="grid gap-4 xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
                    <Card>
                        <CardHeader className="gap-1">
                            <CardTitle>Cashier subscription states</CardTitle>
                            <CardDescription>
                                Current stored states. Grace-period enterprise
                                flows remain provider-owned.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {data.subscriptions.states.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    No provider subscriptions are stored.
                                </p>
                            ) : (
                                <table className="w-full text-sm">
                                    <thead className="text-left text-xs text-muted-foreground uppercase">
                                        <tr>
                                            <th className="py-2 font-medium">
                                                State
                                            </th>
                                            <th className="py-2 text-right font-medium">
                                                Count
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {data.subscriptions.states.map(
                                            (state) => (
                                                <tr
                                                    key={state.state}
                                                    className="border-t"
                                                >
                                                    <td className="py-2 capitalize">
                                                        {state.state.replaceAll(
                                                            '_',
                                                            ' ',
                                                        )}
                                                    </td>
                                                    <td className="py-2 text-right tabular-nums">
                                                        {state.count.toLocaleString()}
                                                    </td>
                                                </tr>
                                            ),
                                        )}
                                    </tbody>
                                </table>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="gap-1">
                            <CardTitle>Resource totals</CardTitle>
                            <CardDescription>
                                Tenant-scoped totals only; destinations and
                                per-workspace drill-down are excluded.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                            {Object.entries(data.resources).map(
                                ([key, value]) => (
                                    <div key={key}>
                                        <p className="text-xs text-muted-foreground capitalize">
                                            {key.replaceAll(/([A-Z])/g, ' $1')}
                                        </p>
                                        <p className="font-medium tabular-nums">
                                            {key === 'storageBytes'
                                                ? `${Math.round(Number(value) / 1024 / 1024).toLocaleString()} MB`
                                                : Number(
                                                      value,
                                                  ).toLocaleString()}
                                        </p>
                                    </div>
                                ),
                            )}
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
                    <Card>
                        <CardHeader className="gap-1">
                            <CardTitle className="flex items-center gap-2">
                                <ShieldCheck
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                Privacy boundary
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-2 text-sm text-muted-foreground">
                            <p>
                                Raw visitor hashes, IP addresses, cities,
                                destinations, and tenant drill-down are never
                                returned.
                            </p>
                            <p>
                                Dimension buckets under{' '}
                                {data.kAnonymityThreshold} events are collapsed
                                into Other before serialization.
                            </p>
                            <Badge variant="outline" className="w-fit">
                                Aggregate only
                            </Badge>
                        </CardContent>
                    </Card>
                </section>
            </div>
        </>
    );
}

function Stat({
    label,
    value,
    children,
}: {
    label: string;
    value: string;
    children: React.ReactNode;
}) {
    return (
        <Card>
            <CardHeader className="gap-1 pb-1">
                <CardDescription>{label}</CardDescription>
                <CardTitle className="text-2xl tabular-nums">{value}</CardTitle>
            </CardHeader>
            <CardContent className="text-xs text-muted-foreground">
                {children}
            </CardContent>
        </Card>
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
                <table className="w-full text-xs">
                    <thead className="sticky top-0 bg-muted text-left uppercase">
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
