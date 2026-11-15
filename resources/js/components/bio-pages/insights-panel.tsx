import { ChartNoAxesCombined, MousePointerClick } from 'lucide-react';
import { CartesianGrid, Line, LineChart, XAxis, YAxis } from 'recharts';
import { RankedBreakdownCard } from '@/components/analytics/ranked-breakdown-card';
import { TrafficSourcesSection } from '@/components/analytics/traffic-sources';
import { StatCard } from '@/components/dashboard/stat-card';
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
    ChartTooltipContent,
    type ChartConfig,
} from '@/components/ui/chart';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { SegmentedControl } from '@/components/ui/segmented-control';
import type { BioPageInsights } from '@/types/bio-pages';

const chartConfig = {
    views: { label: 'Page views', color: 'var(--chart-2)' },
    clicks: { label: 'Button clicks', color: 'var(--chart-4)' },
} satisfies ChartConfig;

type WindowValue = '7' | '30' | '90';

const windowOptions: ReadonlyArray<{ value: WindowValue; label: string }> = [
    { value: '7', label: '7 days' },
    { value: '30', label: '30 days' },
    { value: '90', label: '90 days' },
];

function formatDay(date: string): string {
    return new Intl.DateTimeFormat(undefined, {
        month: 'short',
        day: 'numeric',
    }).format(new Date(`${date}T12:00:00Z`));
}

export function BioPageInsightsPanel({
    insights,
    onWindowChange,
}: {
    insights: BioPageInsights;
    onWindowChange: (days: BioPageInsights['days']) => void;
}) {
    const hasDailyActivity = insights.daily.some(
        (day) => day.views > 0 || day.clicks > 0,
    );
    const clickThroughRate =
        insights.totalViews > 0
            ? (insights.totalClicks / insights.totalViews) * 100
            : 0;

    return (
        <section aria-labelledby="bio-insights-title" className="grid gap-5">
            <div className="flex flex-wrap items-start justify-between gap-4">
                <div className="grid max-w-2xl gap-1">
                    <h2
                        id="bio-insights-title"
                        className="flex items-center gap-2 text-lg font-semibold"
                    >
                        <ChartNoAxesCombined
                            className="size-5 text-muted-foreground"
                            aria-hidden="true"
                        />
                        Page performance
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        Consented page views and button or social clicks for
                        this page. Dates are grouped in UTC.
                    </p>
                </div>
                <div className="grid gap-1">
                    <span className="text-xs font-medium text-muted-foreground">
                        Reporting window
                    </span>
                    <SegmentedControl
                        ariaLabel="Reporting window"
                        value={String(insights.days) as WindowValue}
                        onValueChange={(value) =>
                            onWindowChange(
                                Number(value) as BioPageInsights['days'],
                            )
                        }
                        options={windowOptions}
                    />
                </div>
            </div>

            <div className="grid gap-3 sm:grid-cols-3">
                <StatCard
                    label="Page views"
                    value={insights.totalViews.toLocaleString()}
                    note={`${insights.days} days`}
                    sparkline={insights.daily.map((day) => day.views)}
                    sparklineColor="var(--chart-2)"
                />
                <StatCard
                    label="Button clicks"
                    value={insights.totalClicks.toLocaleString()}
                    note={`${insights.days} days`}
                    sparkline={insights.daily.map((day) => day.clicks)}
                    sparklineColor="var(--chart-4)"
                />
                <StatCard
                    label="Click-through rate"
                    value={`${clickThroughRate.toFixed(1)}%`}
                    note="Clicks ÷ page views"
                />
            </div>

            <div className="grid min-w-0 gap-4 xl:grid-cols-[minmax(0,1fr)_20rem]">
                <Card className="min-w-0">
                    <CardHeader className="gap-1.5">
                        <CardTitle
                            as="h2"
                            className="flex items-center gap-2 text-base"
                        >
                            <ChartNoAxesCombined
                                className="size-4 text-muted-foreground"
                                aria-hidden="true"
                            />
                            Engagement over time
                        </CardTitle>
                        <CardDescription className="text-xs">
                            {insights.startDate} through {insights.endDate} UTC
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="min-w-0">
                        {hasDailyActivity ? (
                            <>
                                <ChartContainer
                                    config={chartConfig}
                                    className="h-[280px] w-full min-w-0"
                                    aria-label="Daily bio page views and button clicks."
                                >
                                    <LineChart
                                        data={insights.daily}
                                        margin={{
                                            top: 8,
                                            right: 16,
                                            bottom: 0,
                                            left: 4,
                                        }}
                                    >
                                        <CartesianGrid vertical={false} />
                                        <XAxis
                                            dataKey="date"
                                            axisLine={false}
                                            tickLine={false}
                                            minTickGap={24}
                                            tickMargin={8}
                                            tick={{
                                                fontFamily: 'var(--font-sans)',
                                                fontSize: 11,
                                                fill: 'var(--muted-foreground)',
                                            }}
                                            tickFormatter={(value) =>
                                                formatDay(String(value))
                                            }
                                        />
                                        <YAxis
                                            allowDecimals={false}
                                            axisLine={false}
                                            tickLine={false}
                                            width={32}
                                            tick={{
                                                fontFamily: 'var(--font-sans)',
                                                fontSize: 11,
                                                fill: 'var(--muted-foreground)',
                                            }}
                                        />
                                        <ChartTooltip
                                            cursor={false}
                                            content={
                                                <ChartTooltipContent
                                                    labelFormatter={(value) =>
                                                        formatDay(String(value))
                                                    }
                                                />
                                            }
                                        />
                                        <Line
                                            dataKey="views"
                                            type="monotone"
                                            stroke="var(--color-views)"
                                            strokeWidth={2}
                                            dot={false}
                                            activeDot={{ r: 4 }}
                                            isAnimationActive={false}
                                        />
                                        <Line
                                            dataKey="clicks"
                                            type="monotone"
                                            stroke="var(--color-clicks)"
                                            strokeWidth={2}
                                            dot={false}
                                            activeDot={{ r: 4 }}
                                            isAnimationActive={false}
                                        />
                                    </LineChart>
                                </ChartContainer>
                                <div className="mt-3 flex flex-wrap gap-4 text-xs text-muted-foreground">
                                    <span className="inline-flex items-center gap-1.5">
                                        <span
                                            className="size-2 rounded-full bg-chart-2"
                                            aria-hidden="true"
                                        />
                                        Page views
                                    </span>
                                    <span className="inline-flex items-center gap-1.5">
                                        <span
                                            className="size-2 rounded-full bg-chart-4"
                                            aria-hidden="true"
                                        />
                                        Button clicks
                                    </span>
                                </div>
                            </>
                        ) : (
                            <Empty>
                                <EmptyHeader>
                                    <EmptyMedia variant="icon">
                                        <ChartNoAxesCombined aria-hidden="true" />
                                    </EmptyMedia>
                                    <EmptyTitle>
                                        No activity in this window
                                    </EmptyTitle>
                                    <EmptyDescription>
                                        Choose another window or check again
                                        after visitors interact with the page.
                                    </EmptyDescription>
                                </EmptyHeader>
                            </Empty>
                        )}
                    </CardContent>
                </Card>

                <RankedBreakdownCard
                    title="Top buttons"
                    description="Button clicks by destination."
                    icon={MousePointerClick}
                    rows={insights.topLinks}
                    emptyTitle="No button clicks in this window."
                    emptyDescription="Choose another window or check again after visitors interact with the page."
                />
            </div>

            <TrafficSourcesSection
                referrers={insights.topReferrers}
                countries={insights.topCountries}
                browsers={insights.topBrowsers}
                platforms={insights.topPlatforms}
            />
        </section>
    );
}
