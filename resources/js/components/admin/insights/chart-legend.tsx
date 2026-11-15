import type { InsightSeries } from '@/components/admin/insights/daily-chart';

export function ChartLegend({
    series,
    values,
}: {
    series: readonly InsightSeries[];
    values: Record<string, number>;
}) {
    return (
        <ul className="flex flex-wrap items-center gap-x-6 gap-y-2 text-xs text-muted-foreground">
            {series.map((item) => (
                <li key={item.key} className="flex items-center gap-2">
                    <span
                        className="size-2 rounded-[2px]"
                        style={{ background: item.color }}
                        aria-hidden="true"
                    />
                    <span>{item.label}</span>
                    <span className="text-foreground tabular-nums">
                        {values[item.key]?.toLocaleString() ?? '0'}
                    </span>
                </li>
            ))}
        </ul>
    );
}
