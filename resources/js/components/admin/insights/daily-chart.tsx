import {
    Bar,
    BarChart,
    CartesianGrid,
    Line,
    LineChart,
    XAxis,
    YAxis,
} from 'recharts';
import {
    ChartContainer,
    ChartTooltip,
    ChartTooltipContent,
    type ChartConfig,
} from '@/components/ui/chart';

export type InsightSeries = {
    key: string;
    label: string;
    color: string;
};

const axisTick = {
    fontFamily: 'var(--font-sans)',
    fontSize: 11,
    fill: 'var(--muted-foreground)',
};

export function formatDay(value: string): string {
    return new Intl.DateTimeFormat(undefined, {
        month: 'short',
        day: 'numeric',
        timeZone: 'UTC',
    }).format(new Date(`${value}T00:00:00Z`));
}

export function formatDayDetailed(value: string): string {
    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeZone: 'UTC',
    }).format(new Date(`${value}T00:00:00Z`));
}

function seriesConfig(series: InsightSeries[]): ChartConfig {
    return Object.fromEntries(
        series.map((item) => [
            item.key,
            { label: item.label, color: item.color },
        ]),
    ) satisfies ChartConfig;
}

export function InsightDailyChart({
    data,
    series,
    label,
    tall = false,
}: {
    data: Array<Record<string, string | number>>;
    series: InsightSeries[];
    label: string;
    tall?: boolean;
}) {
    return (
        <ChartContainer
            config={seriesConfig(series)}
            className={`${tall ? 'h-[300px]' : 'h-[240px]'} w-full min-w-0`}
            aria-label={label}
        >
            <LineChart
                data={data}
                margin={{ top: 8, right: 24, bottom: 0, left: 0 }}
            >
                <CartesianGrid vertical={false} />
                <XAxis
                    dataKey="date"
                    axisLine={false}
                    tickLine={false}
                    minTickGap={24}
                    tickMargin={8}
                    tick={axisTick}
                    tickFormatter={(value) => formatDay(String(value))}
                />
                <YAxis
                    allowDecimals={false}
                    axisLine={false}
                    tickLine={false}
                    width={36}
                    tick={axisTick}
                />
                <ChartTooltip
                    cursor={false}
                    content={
                        <ChartTooltipContent
                            labelFormatter={(value) =>
                                formatDayDetailed(String(value))
                            }
                        />
                    }
                />
                {series.map((item) => (
                    <Line
                        key={item.key}
                        dataKey={item.key}
                        type="monotone"
                        stroke={`var(--color-${item.key})`}
                        strokeWidth={2}
                        dot={false}
                        activeDot={{ r: 4 }}
                        isAnimationActive={false}
                    />
                ))}
            </LineChart>
        </ChartContainer>
    );
}

export function InsightDailyBarChart({
    data,
    series,
    label,
    height = 240,
}: {
    data: Array<Record<string, string | number>>;
    series: InsightSeries[];
    label: string;
    height?: number;
}) {
    return (
        <ChartContainer
            config={seriesConfig(series)}
            className="w-full min-w-0"
            style={{ height }}
            aria-label={label}
        >
            <BarChart
                data={data}
                margin={{ top: 8, right: 24, bottom: 0, left: 0 }}
            >
                <CartesianGrid vertical={false} />
                <XAxis
                    dataKey="date"
                    axisLine={false}
                    tickLine={false}
                    minTickGap={24}
                    tickMargin={8}
                    tick={axisTick}
                    tickFormatter={(value) => formatDay(String(value))}
                />
                <YAxis
                    allowDecimals={false}
                    axisLine={false}
                    tickLine={false}
                    width={36}
                    tick={axisTick}
                />
                <ChartTooltip
                    content={
                        <ChartTooltipContent
                            labelFormatter={(value) =>
                                formatDayDetailed(String(value))
                            }
                        />
                    }
                />
                {series.map((item) => (
                    <Bar
                        key={item.key}
                        dataKey={item.key}
                        stackId="daily"
                        fill={`var(--color-${item.key})`}
                        maxBarSize={24}
                        isAnimationActive={false}
                    />
                ))}
            </BarChart>
        </ChartContainer>
    );
}
