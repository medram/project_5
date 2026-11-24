import {
    Compass,
    Globe,
    Laptop,
    MonitorSmartphone,
    Share2,
    type LucideIcon,
} from 'lucide-react';
import { type ComponentProps, type ReactNode } from 'react';
import WorldMap from 'react-svg-worldmap';
import { Cell, Pie, PieChart as RechartsPieChart } from 'recharts';
import { CountryFlag } from '@/components/analytics/country-flag';
import { RankedBreakdownCard } from '@/components/analytics/ranked-breakdown-card';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    ChartContainer,
    ChartTooltip,
    type ChartConfig,
} from '@/components/ui/chart';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import type { AnalyticsCount } from '@/types/analytics';

const chartColors = [
    'var(--chart-1)',
    'var(--chart-2)',
    'var(--chart-3)',
    'var(--chart-4)',
    'var(--chart-5)',
] as const;

const percentageFormatter = new Intl.NumberFormat(undefined, {
    maximumFractionDigits: 1,
});

const regionDisplayNames =
    typeof Intl.DisplayNames === 'function'
        ? new Intl.DisplayNames(undefined, { type: 'region' })
        : null;

type WorldMapData = ComponentProps<typeof WorldMap>['data'];
type WorldMapCountry = WorldMapData[number]['country'];
type TrafficMetric = 'events' | 'scans';

type BreakdownDatum = AnalyticsCount & {
    fill: string;
    percentage: number;
};

function trafficUnit(metric: TrafficMetric): string {
    return metric === 'scans' ? 'scans' : 'events';
}

function trafficActivity(metric: TrafficMetric): string {
    return metric === 'scans' ? 'QR scans' : 'Consented events';
}

function trafficSubject(metric: TrafficMetric): string {
    return metric === 'scans' ? 'QR scans' : 'consented visitors';
}

function emptyWindowDescription(metric: TrafficMetric): string {
    return `Choose another window or check again after ${trafficSubject(metric)} arrive.`;
}

function dimensionEmptyDescription(metric: TrafficMetric): string {
    return metric === 'scans'
        ? 'This dimension appears only on scans recorded with request metadata.'
        : emptyWindowDescription(metric);
}

function sumCounts(rows: AnalyticsCount[]): number {
    return rows.reduce((total, row) => total + row.count, 0);
}

function formatPercentage(value: number): string {
    return `${percentageFormatter.format(value)}%`;
}

function toBreakdownData(rows: AnalyticsCount[]): BreakdownDatum[] {
    const total = sumCounts(rows);

    return rows.map((row, index) => ({
        ...row,
        fill: chartColors[index] ?? chartColors[chartColors.length - 1],
        percentage: total > 0 ? (row.count / total) * 100 : 0,
    }));
}

function countryName(code: string): string {
    const normalized = code.trim().toUpperCase();

    if (!/^[A-Z]{2}$/.test(normalized)) {
        return code;
    }

    try {
        return regionDisplayNames?.of(normalized) ?? normalized;
    } catch {
        return normalized;
    }
}

function toMapData(rows: AnalyticsCount[]): WorldMapData {
    return rows.flatMap((row) => {
        const country = row.name.trim().toLowerCase();

        if (!/^[a-z]{2}$/.test(country)) {
            return [];
        }

        return [
            {
                country: country as WorldMapCountry,
                value: row.count,
            },
        ];
    });
}

function CardHeading({
    icon: Icon,
    children,
}: {
    icon: LucideIcon;
    children: ReactNode;
}) {
    return (
        <CardTitle as="h2" className="flex items-center gap-2">
            <Icon
                className="size-4 shrink-0 text-muted-foreground"
                aria-hidden="true"
            />
            {children}
        </CardTitle>
    );
}

function EmptyBreakdown({
    icon: Icon,
    title,
    description,
}: {
    icon: LucideIcon;
    title: string;
    description: string;
}) {
    return (
        <Empty>
            <EmptyHeader>
                <EmptyMedia variant="icon">
                    <Icon aria-hidden="true" />
                </EmptyMedia>
                <EmptyTitle>{title}</EmptyTitle>
                <EmptyDescription>{description}</EmptyDescription>
            </EmptyHeader>
        </Empty>
    );
}

function CountryLegend({
    rows,
    metric,
}: {
    rows: AnalyticsCount[];
    metric: TrafficMetric;
}) {
    const total = sumCounts(rows);

    return (
        <div className="grid min-w-0 gap-3">
            <div className="flex items-center justify-between gap-3 text-xs text-muted-foreground">
                <span>Ranked by {trafficUnit(metric)}</span>
                <span className="tabular-nums">
                    {total.toLocaleString()}{' '}
                    {metric === 'scans' ? 'scans' : 'listed'}
                </span>
            </div>
            <div
                className="flex items-center gap-2 text-[11px] text-muted-foreground"
                aria-hidden="true"
            >
                <span>Fewer</span>
                <span
                    className="h-1.5 flex-1 rounded-full"
                    style={{
                        background:
                            'linear-gradient(to right, color-mix(in oklab, var(--chart-2) 20%, var(--card)), var(--chart-2))',
                    }}
                />
                <span>More</span>
            </div>
            <ol
                className="grid gap-2"
                aria-label={`Top countries by ${trafficUnit(metric)}`}
            >
                {rows.map((row, index) => {
                    const percentage =
                        total > 0 ? (row.count / total) * 100 : 0;

                    return (
                        <li
                            key={row.name}
                            className="flex items-center gap-3 rounded-lg border border-border/70 bg-card px-3 py-2"
                        >
                            <span
                                className="flex size-5 shrink-0 items-center justify-center rounded-full bg-primary-soft text-[11px] font-semibold text-primary-soft-foreground tabular-nums"
                                aria-hidden="true"
                            >
                                {index + 1}
                            </span>
                            <span className="flex min-w-0 flex-1 items-center gap-2.5">
                                <CountryFlag code={row.name} />
                                <span className="min-w-0">
                                    <span className="block truncate text-sm">
                                        {countryName(row.name)}
                                    </span>
                                    <span className="block text-[11px] text-muted-foreground">
                                        {row.name.toUpperCase()}
                                    </span>
                                </span>
                            </span>
                            <span className="shrink-0 text-right">
                                <span className="block text-sm font-medium tabular-nums">
                                    {row.count.toLocaleString()}
                                </span>
                                <span className="block text-[11px] text-muted-foreground tabular-nums">
                                    {formatPercentage(percentage)}
                                </span>
                            </span>
                        </li>
                    );
                })}
            </ol>
        </div>
    );
}

export function TopCountriesCard({
    rows,
    metric,
    footer,
}: {
    rows: AnalyticsCount[];
    metric: TrafficMetric;
    footer?: string;
}) {
    const total = sumCounts(rows);
    const mapData = toMapData(rows);

    return (
        <Card className="col-span-full min-w-0">
            <CardHeader className="gap-1.5">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div className="grid gap-1">
                        <CardHeading icon={Globe}>Top countries</CardHeading>
                        <CardDescription className="text-xs">
                            {trafficActivity(metric)} by country. Darker
                            countries rank higher.
                        </CardDescription>
                    </div>
                    {rows.length > 0 && (
                        <span className="text-xs text-muted-foreground tabular-nums">
                            {total.toLocaleString()} {trafficUnit(metric)}
                        </span>
                    )}
                </div>
            </CardHeader>
            <CardContent>
                {rows.length === 0 ? (
                    <EmptyBreakdown
                        icon={Globe}
                        title="No country data in this window"
                        description={dimensionEmptyDescription(metric)}
                    />
                ) : (
                    <div className="grid min-w-0 gap-5 lg:grid-cols-[minmax(0,1fr)_20rem] lg:items-center">
                        <div className="mx-auto w-full max-w-3xl min-w-0">
                            <WorldMap
                                data={mapData}
                                title={`Top countries by ${
                                    metric === 'scans'
                                        ? 'QR scans'
                                        : 'consented events'
                                }`}
                                size="responsive"
                                projection="mercator"
                                frame={false}
                                backgroundColor="transparent"
                                borderColor="var(--border)"
                                strokeOpacity={0.75}
                                color="var(--chart-2)"
                                containerClassName="analytics-map w-full"
                                tooltipBgColor="var(--popover)"
                                tooltipTextColor="var(--popover-foreground)"
                                tooltipTextFunction={({
                                    countryName: mapCountryName,
                                    countryValue,
                                }) =>
                                    countryValue === undefined
                                        ? mapCountryName
                                        : `${mapCountryName}: ${countryValue.toLocaleString()} ${trafficUnit(metric)}`
                                }
                                styleFunction={({
                                    countryValue,
                                    minValue,
                                    maxValue,
                                }) => {
                                    if (typeof countryValue !== 'number') {
                                        return {
                                            fill: 'var(--muted-foreground)',
                                            fillOpacity: 0.18,
                                            stroke: 'var(--border)',
                                            strokeWidth: 0.6,
                                        };
                                    }

                                    const range = maxValue - minValue;
                                    const ratio =
                                        range === 0
                                            ? 1
                                            : (countryValue - minValue) / range;

                                    return {
                                        fill: 'var(--chart-2)',
                                        fillOpacity: 0.35 + ratio * 0.65,
                                        stroke: 'var(--card)',
                                        strokeWidth: 0.8,
                                    };
                                }}
                            />
                        </div>
                        <CountryLegend rows={rows} metric={metric} />
                    </div>
                )}
                {footer ? (
                    <p className="mt-4 text-xs text-muted-foreground">
                        {footer}
                    </p>
                ) : null}
            </CardContent>
        </Card>
    );
}

type PieTooltipItem = {
    name?: string | number;
    value?: string | number;
    payload?: {
        fill?: string;
        percentage?: number;
    };
};

function PieTooltipContent({
    active,
    payload,
    metric,
}: {
    active?: boolean;
    payload?: readonly PieTooltipItem[];
    metric: TrafficMetric;
}) {
    const item = payload?.[0];

    if (!active || !item) {
        return null;
    }

    return (
        <div className="grid min-w-36 gap-1.5 rounded-lg border border-border/50 bg-background px-2.5 py-1.5 text-xs shadow-xl">
            <div className="flex items-center gap-2 font-medium">
                {item.payload?.fill && (
                    <span
                        className="size-2 shrink-0 rounded-[2px]"
                        style={{ backgroundColor: item.payload.fill }}
                        aria-hidden="true"
                    />
                )}
                {item.name}
            </div>
            <div className="flex items-center justify-between gap-4">
                <span className="text-muted-foreground">
                    {metric === 'scans' ? 'Scans' : 'Events'}
                </span>
                <span className="font-medium tabular-nums">
                    {Number(item.value ?? 0).toLocaleString()}
                </span>
            </div>
            <div className="flex items-center justify-between gap-4">
                <span className="text-muted-foreground">Share of top 5</span>
                <span className="font-medium tabular-nums">
                    {formatPercentage(item.payload?.percentage ?? 0)}
                </span>
            </div>
        </div>
    );
}

export function PieBreakdownCard({
    title,
    description,
    icon,
    rows,
    emptyTitle,
    emptyDescription,
    metric,
}: {
    title: string;
    description: string;
    icon: LucideIcon;
    rows: AnalyticsCount[];
    emptyTitle: string;
    emptyDescription: string;
    metric: TrafficMetric;
}) {
    const data = toBreakdownData(rows);
    const total = sumCounts(rows);
    const chartConfig = {
        count: {
            label: metric === 'scans' ? 'Scans' : 'Events',
            color: 'var(--chart-2)',
        },
    } satisfies ChartConfig;

    return (
        <Card className="min-w-0">
            <CardHeader className="gap-1.5">
                <div className="grid gap-1">
                    <CardHeading icon={icon}>{title}</CardHeading>
                    <CardDescription className="text-xs">
                        {description}
                    </CardDescription>
                </div>
            </CardHeader>
            <CardContent>
                {rows.length === 0 ? (
                    <EmptyBreakdown
                        icon={icon}
                        title={emptyTitle}
                        description={emptyDescription}
                    />
                ) : (
                    <div className="grid gap-4">
                        <div className="relative mx-auto h-[190px] w-[190px] max-w-full">
                            <ChartContainer
                                config={chartConfig}
                                className="h-full w-full"
                                aria-label={`${title} distribution for the top ${rows.length} groups. Exact counts and percentages are listed below.`}
                            >
                                <RechartsPieChart>
                                    <ChartTooltip
                                        content={
                                            <PieTooltipContent
                                                metric={metric}
                                            />
                                        }
                                    />
                                    <Pie
                                        data={data}
                                        dataKey="count"
                                        nameKey="name"
                                        cx="50%"
                                        cy="50%"
                                        innerRadius="55%"
                                        outerRadius="92%"
                                        stroke="var(--card)"
                                        strokeWidth={2}
                                        isAnimationActive={false}
                                    >
                                        {data.map((entry) => (
                                            <Cell
                                                key={entry.name}
                                                fill={entry.fill}
                                            />
                                        ))}
                                    </Pie>
                                </RechartsPieChart>
                            </ChartContainer>
                            <div
                                className="pointer-events-none absolute inset-0 flex flex-col items-center justify-center gap-0.5"
                                aria-hidden="true"
                            >
                                <span className="text-xl font-semibold tabular-nums">
                                    {total.toLocaleString()}
                                </span>
                                <span className="text-xs text-muted-foreground">
                                    {trafficUnit(metric)}
                                </span>
                            </div>
                        </div>
                        <ul
                            className="grid gap-2"
                            aria-label={`${title} values`}
                        >
                            {data.map((row) => (
                                <li
                                    key={row.name}
                                    className="flex items-center gap-2 text-xs"
                                >
                                    <span
                                        className="size-2.5 shrink-0 rounded-[3px]"
                                        style={{ backgroundColor: row.fill }}
                                        aria-hidden="true"
                                    />
                                    <span className="min-w-0 flex-1 truncate text-muted-foreground">
                                        {row.name}
                                    </span>
                                    <span className="shrink-0 font-medium tabular-nums">
                                        {row.count.toLocaleString()}
                                    </span>
                                    <span className="w-12 shrink-0 text-right text-muted-foreground tabular-nums">
                                        {formatPercentage(row.percentage)}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

export function TrafficSourcesSection({
    referrers,
    countries,
    browsers,
    platforms,
    devices,
    metric = 'events',
    countryFooter,
}: {
    referrers: AnalyticsCount[];
    countries: AnalyticsCount[];
    browsers: AnalyticsCount[];
    platforms: AnalyticsCount[];
    devices?: AnalyticsCount[];
    metric?: TrafficMetric;
    countryFooter?: string;
}) {
    return (
        <section
            aria-label="Traffic sources"
            className={`grid min-w-0 gap-4 ${
                devices === undefined
                    ? 'lg:grid-cols-3'
                    : 'lg:grid-cols-2 xl:grid-cols-4'
            }`}
        >
            <TopCountriesCard
                rows={countries}
                metric={metric}
                footer={countryFooter}
            />
            {devices !== undefined && (
                <RankedBreakdownCard
                    title="Top devices"
                    description="Share of scans by device class."
                    icon={Laptop}
                    rows={devices}
                    emptyTitle="No device data in this window"
                    emptyDescription={dimensionEmptyDescription(metric)}
                />
            )}
            <RankedBreakdownCard
                title="Top referrers"
                description={
                    metric === 'scans'
                        ? 'Domains that referred QR scans.'
                        : 'Domains that sent consented visitors.'
                }
                icon={Share2}
                rows={referrers}
                emptyTitle={
                    metric === 'scans'
                        ? 'No referrer data in this window'
                        : 'No data in this window'
                }
                emptyDescription={
                    metric === 'scans'
                        ? 'QR scans usually open directly; a referrer appears only when the browser sends one.'
                        : emptyWindowDescription(metric)
                }
            />
            <PieBreakdownCard
                title="Top browsers"
                description="Share of the five largest browser cohorts."
                icon={Compass}
                rows={browsers}
                emptyTitle="No browser data in this window"
                emptyDescription={dimensionEmptyDescription(metric)}
                metric={metric}
            />
            <PieBreakdownCard
                title="Top platforms"
                description="Share of the five largest platform cohorts."
                icon={MonitorSmartphone}
                rows={platforms}
                emptyTitle="No platform data in this window"
                emptyDescription={dimensionEmptyDescription(metric)}
                metric={metric}
            />
        </section>
    );
}
