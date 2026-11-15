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

export function InsightDailySmallMultiples({
    data,
    series,
    values,
    label,
}: {
    data: Array<Record<string, string | number>>;
    series: InsightSeries[];
    values: Record<string, number>;
    label: string;
}) {
    return (
        <div
            data-slot="daily-small-multiples"
            className="grid gap-3"
            role="group"
            aria-label={label}
        >
            {series.map((item, index) => {
                const total = values[item.key] ?? 0;
                const max = Math.max(
                    ...data.map((row) => Number(row[item.key] ?? 0)),
                    0,
                );
                const isLast = index === series.length - 1;

                return (
                    <div key={item.key} className="grid gap-1.5">
                        <div className="flex items-baseline justify-between gap-3 text-xs">
                            <span className="flex items-center gap-2 text-muted-foreground">
                                <span
                                    className="size-2 rounded-[2px]"
                                    style={{ background: item.color }}
                                    aria-hidden="true"
                                />
                                {item.label}
                            </span>
                            <span className="text-foreground tabular-nums">
                                {total.toLocaleString()}
                            </span>
                        </div>
                        <ChartContainer
                            config={
                                {
                                    [item.key]: {
                                        label: item.label,
                                        color: item.color,
                                    },
                                } satisfies ChartConfig
                            }
                            className="h-[80px] w-full min-w-0"
                            aria-label={`${item.label} per UTC day. Window total ${total.toLocaleString()}, daily peak ${max.toLocaleString()}.`}
                        >
                            <LineChart
                                data={data}
                                margin={{
                                    top: 4,
                                    right: 24,
                                    bottom: 0,
                                    left: 0,
                                }}
                            >
                                <XAxis
                                    dataKey="date"
                                    hide={!isLast}
                                    axisLine={false}
                                    tickLine={false}
                                    minTickGap={24}
                                    tickMargin={6}
                                    tick={axisTick}
                                    tickFormatter={(value) =>
                                        formatDay(String(value))
                                    }
                                />
                                <YAxis
                                    allowDecimals={false}
                                    axisLine={false}
                                    tickLine={false}
                                    width={36}
                                    domain={[0, max || 1]}
                                    ticks={max > 0 ? [0, max] : [0]}
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
                                <Line
                                    dataKey={item.key}
                                    type="monotone"
                                    stroke={`var(--color-${item.key})`}
                                    strokeWidth={2}
                                    dot={false}
                                    activeDot={{ r: 3 }}
                                    isAnimationActive={false}
                                />
                            </LineChart>
                        </ChartContainer>
                    </div>
                );
            })}
        </div>
    );
}

export function InsightDailyBarChart({
    data,
    series,
    label,
}: {
    data: Array<Record<string, string | number>>;
    series: InsightSeries[];
    label: string;
}) {
    return (
        <ChartContainer
            config={seriesConfig(series)}
            className="h-[240px] w-full min-w-0"
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
