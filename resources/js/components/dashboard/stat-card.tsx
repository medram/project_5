import { ArrowDownRight, ArrowUpRight, type LucideIcon } from 'lucide-react';
import { type ReactNode, useId } from 'react';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

export type StatTrend = {
    value: string;
    direction: 'up' | 'down';
    positive: boolean;
};

type StatCardProps = {
    label: string;
    value: ReactNode;
    icon?: LucideIcon;
    note?: string;
    trend?: StatTrend;
    sparkline?: number[];
    sparklineColor?: string;
    className?: string;
};

function sparklineShape(values: number[]) {
    const width = 100;
    const height = 32;
    const max = Math.max(...values);
    const min = Math.min(...values);
    const span = max - min;
    const step = width / Math.max(values.length - 1, 1);
    const points = values.map((value, index) => {
        const x = index * step;
        const y =
            span === 0
                ? height / 2
                : height - 3 - ((value - min) / span) * (height - 8);

        return [x, y] as const;
    });

    const line = points
        .map(
            ([x, y], index) =>
                `${index === 0 ? 'M' : 'L'}${x.toFixed(2)} ${y.toFixed(2)}`,
        )
        .join(' ');
    const area = `${line} L${width} ${height} L0 ${height} Z`;

    return { line, area };
}

export function StatCard({
    label,
    value,
    icon: Icon,
    note,
    trend,
    sparkline,
    sparklineColor = 'var(--primary)',
    className,
}: StatCardProps) {
    const gradientId = useId().replaceAll(':', '');
    const TrendIcon =
        trend?.direction === 'down' ? ArrowDownRight : ArrowUpRight;
    const shape =
        sparkline && sparkline.length > 1
            ? sparklineShape(sparkline)
            : undefined;
    const iconAtTop = Boolean(Icon && (note || trend || shape));

    return (
        <div
            data-slot="stat-card"
            className={cn(
                'relative flex flex-col justify-between gap-3 overflow-hidden rounded-2xl border border-border bg-card p-4 shadow-[var(--shadow-surface)]',
                sparkline ? 'min-h-[118px] pb-0' : 'min-h-[94px]',
                className,
            )}
        >
            <p
                className={cn(
                    'text-[13px] leading-[18px] font-medium text-muted-foreground',
                    iconAtTop && 'min-h-10 pr-12',
                )}
            >
                {label}
            </p>
            <div
                className={cn(
                    'flex items-end justify-between gap-3',
                    Icon && !iconAtTop && 'pr-12',
                )}
            >
                <p className="text-2xl leading-7 font-semibold tracking-[-0.02em] tabular-nums">
                    {value}
                </p>
                {trend ? (
                    <Badge
                        variant={trend.positive ? 'success' : 'destructive'}
                        className="mb-0.5 gap-1"
                    >
                        <TrendIcon aria-hidden="true" />
                        {trend.value}
                    </Badge>
                ) : note ? (
                    <span className="mb-1 hidden truncate text-right text-xs leading-4 text-muted-foreground sm:block">
                        {note}
                    </span>
                ) : null}
            </div>
            {shape && (
                <div aria-hidden="true" className="-mx-4 mt-1 -mb-4 h-10">
                    <svg
                        viewBox="0 0 100 32"
                        preserveAspectRatio="none"
                        className="h-full w-full"
                    >
                        <defs>
                            <linearGradient
                                id={gradientId}
                                x1="0"
                                y1="0"
                                x2="0"
                                y2="1"
                            >
                                <stop
                                    offset="0%"
                                    stopColor={sparklineColor}
                                    stopOpacity="0.22"
                                />
                                <stop
                                    offset="100%"
                                    stopColor={sparklineColor}
                                    stopOpacity="0"
                                />
                            </linearGradient>
                        </defs>
                        <path d={shape.area} fill={`url(#${gradientId})`} />
                        <path
                            d={shape.line}
                            fill="none"
                            stroke={sparklineColor}
                            strokeWidth="1.5"
                            strokeLinecap="round"
                            vectorEffect="non-scaling-stroke"
                        />
                    </svg>
                </div>
            )}
            {Icon && (
                <span
                    aria-hidden="true"
                    className={cn(
                        'pointer-events-none absolute right-4 flex size-10 items-center justify-center rounded-xl bg-primary-soft text-primary-soft-foreground',
                        iconAtTop ? 'top-4' : 'bottom-4',
                    )}
                >
                    <Icon className="size-5" />
                </span>
            )}
        </div>
    );
}
