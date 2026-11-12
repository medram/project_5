import { CartesianGrid, Line, LineChart, XAxis, YAxis } from 'recharts';
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

export function InsightDailyChart({
    data,
    series,
    label,
}: {
    data: Array<Record<string, string | number>>;
    series: InsightSeries[];
    label: string;
}) {
    const config = Object.fromEntries(
        series.map((item) => [
            item.key,
            { label: item.label, color: item.color },
        ]),
    ) satisfies ChartConfig;

    return (
        <ChartContainer
            config={config}
            className="h-[260px] w-full min-w-0"
            aria-label={label}
        >
            <LineChart
                data={data}
                margin={{ top: 8, right: 8, bottom: 0, left: 0 }}
            >
                <CartesianGrid vertical={false} />
                <XAxis
                    dataKey="date"
                    axisLine={false}
                    tickLine={false}
                    minTickGap={24}
                    tickMargin={8}
                    tickFormatter={(value: string) =>
                        new Intl.DateTimeFormat(undefined, {
                            month: 'short',
                            day: 'numeric',
                        }).format(new Date(`${value}T00:00:00Z`))
                    }
                />
                <YAxis
                    allowDecimals={false}
                    axisLine={false}
                    tickLine={false}
                    width={40}
                />
                <ChartTooltip
                    cursor={false}
                    content={
                        <ChartTooltipContent
                            labelFormatter={(value) =>
                                new Intl.DateTimeFormat(undefined, {
                                    dateStyle: 'medium',
                                }).format(
                                    new Date(`${String(value)}T00:00:00Z`),
                                )
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
