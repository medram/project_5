import * as React from 'react';
import {
    ResponsiveContainer,
    Tooltip as RechartsTooltip,
} from 'recharts';
import { cn } from '@/lib/utils';

type ChartConfig = Record<
    string,
    {
        label?: React.ReactNode;
        color?: string;
    }
>;

type ChartContextValue = { config: ChartConfig };

const ChartContext = React.createContext<ChartContextValue | null>(null);

function ChartContainer({
    id,
    className,
    children,
    config,
    style,
    ...props
}: React.ComponentProps<'div'> & {
    config: ChartConfig;
    children: React.ComponentProps<typeof ResponsiveContainer>['children'];
}) {
    const generatedId = React.useId().replaceAll(':', '');
    const chartId = `chart-${id ?? generatedId}`;
    const colors = Object.fromEntries(
        Object.entries(config).flatMap(([key, value]) =>
            value.color ? [[`--color-${key}`, value.color]] : [],
        ),
    ) as React.CSSProperties;

    return (
        <ChartContext.Provider value={{ config }}>
            <div
                data-slot="chart"
                data-chart={chartId}
                role="img"
                className={cn(
                    'flex w-full justify-center text-xs [&_.recharts-cartesian-axis-tick_text]:fill-muted-foreground [&_.recharts-cartesian-grid_line]:stroke-border/50 [&_.recharts-layer]:outline-hidden [&_.recharts-surface]:outline-hidden',
                    className,
                )}
                style={{ ...colors, ...style }}
                {...props}
            >
                <ResponsiveContainer width="100%" height="100%">
                    {children}
                </ResponsiveContainer>
            </div>
        </ChartContext.Provider>
    );
}

type TooltipEntry = {
    dataKey?: string | number;
    name?: string | number;
    value?: string | number | null;
    color?: string;
};

type ChartTooltipContentProps = {
    active?: boolean;
    payload?: TooltipEntry[];
    label?: string | number;
    labelFormatter?: (label: string | number) => React.ReactNode;
};

function ChartTooltipContent({
    active,
    payload,
    label,
    labelFormatter,
}: ChartTooltipContentProps) {
    const context = React.useContext(ChartContext);

    if (!active || !payload?.length) {
        return null;
    }

    return (
        <div className="grid min-w-32 gap-1.5 rounded-lg border border-border/50 bg-background px-2.5 py-1.5 text-xs shadow-xl">
            {label !== undefined && (
                <div className="font-medium">
                    {labelFormatter ? labelFormatter(label) : label}
                </div>
            )}
            {payload.map((item, index) => {
                const key = String(item.dataKey ?? item.name ?? 'value');
                const config = context?.config[key];
                const color = item.color ?? config?.color;

                return (
                    <div
                        key={`${key}-${index}`}
                        className="flex items-center justify-between gap-4"
                    >
                        <span className="flex items-center gap-2">
                            {color && (
                                <span
                                    aria-hidden="true"
                                    className="size-2 shrink-0 rounded-[2px]"
                                    style={{ backgroundColor: color }}
                                />
                            )}
                            <span className="text-muted-foreground">
                                {config?.label ?? item.name ?? key}
                            </span>
                        </span>
                        <span className="font-mono font-medium tabular-nums">
                            {typeof item.value === 'number'
                                ? item.value.toLocaleString()
                                : item.value}
                        </span>
                    </div>
                );
            })}
        </div>
    );
}

const ChartTooltip = RechartsTooltip;

export { ChartContainer, ChartTooltip, ChartTooltipContent };
export type { ChartConfig };
