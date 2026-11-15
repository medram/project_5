import { BarChart3, type LucideIcon } from 'lucide-react';
import { CartesianGrid, Line, LineChart, XAxis, YAxis } from 'recharts';
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

export function formatChartDay(date: string): string {
    return new Intl.DateTimeFormat(undefined, {
        month: 'short',
        day: 'numeric',
    }).format(new Date(`${date}T12:00:00Z`));
}

export function DailyLineChart({
    data,
    dataKey,
    label,
    ariaLabel,
    emptyTitle,
    emptyDescription,
    color = 'var(--chart-1)',
    height = 300,
    icon: Icon = BarChart3,
}: {
    data: Array<Record<string, string | number>>;
    dataKey: string;
    label: string;
    ariaLabel: string;
    emptyTitle: string;
    emptyDescription: string;
    color?: string;
    height?: number;
    icon?: LucideIcon;
}) {
    const hasData = data.some((row) => Number(row[dataKey] ?? 0) > 0);
    const config = {
        [dataKey]: { label, color },
    } satisfies ChartConfig;

    if (!hasData) {
        return (
            <Empty>
                <EmptyHeader>
                    <EmptyMedia variant="icon">
                        <Icon aria-hidden="true" />
                    </EmptyMedia>
                    <EmptyTitle>{emptyTitle}</EmptyTitle>
                    <EmptyDescription>{emptyDescription}</EmptyDescription>
                </EmptyHeader>
            </Empty>
        );
    }

    return (
        <ChartContainer
            config={config}
            className="w-full min-w-0"
            style={{ height }}
            aria-label={ariaLabel}
        >
            <LineChart
                data={data}
                margin={{ top: 8, right: 24, bottom: 0, left: 8 }}
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
                    tickFormatter={(value) => formatChartDay(String(value))}
                />
                <YAxis
                    allowDecimals={false}
                    axisLine={false}
                    tickLine={false}
                    width={36}
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
                                formatChartDay(String(value))
                            }
                        />
                    }
                />
                <Line
                    dataKey={dataKey}
                    type="monotone"
                    stroke={`var(--color-${dataKey})`}
                    strokeWidth={2}
                    dot={false}
                    activeDot={{ r: 4 }}
                    isAnimationActive={false}
                />
            </LineChart>
        </ChartContainer>
    );
}
