import { Cell, Pie, PieChart } from 'recharts';
import { ChartContainer } from '@/components/ui/chart';
import type { AdminDimension } from '@/types/admin-insights';

const palette = [
    'var(--chart-1)',
    'var(--chart-2)',
    'var(--chart-3)',
    'var(--chart-4)',
    'var(--chart-5)',
];

const shareFormatter = new Intl.NumberFormat(undefined, {
    style: 'percent',
    maximumFractionDigits: 1,
});

function swatch(index: number): string {
    return palette[index] ?? 'var(--chart-5)';
}

export function DimensionDonut({
    rows,
    label,
    nameFormatter = (name) => name,
    totalLabel,
}: {
    rows: AdminDimension[];
    label: string;
    nameFormatter?: (name: string) => string;
    totalLabel: string;
}) {
    if (rows.length === 0) {
        return null;
    }

    const total = rows.reduce((sum, row) => sum + row.count, 0);
    const chartRows = rows.map((row, index) => ({
        label: nameFormatter(row.name),
        value: row.count,
        fill: swatch(index),
    }));
    const ariaLabel = `${label}: ${rows
        .map((row) => `${nameFormatter(row.name)} ${row.count}`)
        .join(', ')}`;

    return (
        <div data-slot="dimension-donut" className="grid gap-4">
            <div className="relative mx-auto w-full max-w-[220px]">
                <ChartContainer
                    config={{}}
                    className="h-[180px] w-full"
                    aria-label={ariaLabel}
                >
                    <PieChart>
                        <Pie
                            data={chartRows}
                            dataKey="value"
                            nameKey="label"
                            innerRadius={54}
                            outerRadius={78}
                            paddingAngle={2}
                            stroke="var(--card)"
                            strokeWidth={2}
                            isAnimationActive={false}
                        >
                            {chartRows.map((row, index) => (
                                <Cell key={index} fill={row.fill} />
                            ))}
                        </Pie>
                    </PieChart>
                </ChartContainer>
                <div
                    className="pointer-events-none absolute inset-0 flex flex-col items-center justify-center gap-1"
                    aria-hidden="true"
                >
                    <span className="text-xl leading-none font-semibold tabular-nums">
                        {total.toLocaleString()}
                    </span>
                    <span className="text-xs text-muted-foreground">
                        {totalLabel}
                    </span>
                </div>
            </div>
            <ul className="grid gap-2">
                {rows.map((row, index) => {
                    const name = nameFormatter(row.name);
                    const share = total > 0 ? row.count / total : 0;

                    return (
                        <li
                            key={row.name}
                            className="flex items-center gap-2 text-xs"
                        >
                            <span
                                className="size-2 shrink-0 rounded-[2px]"
                                style={{ background: swatch(index) }}
                                aria-hidden="true"
                            />
                            <span className="min-w-0 truncate" title={name}>
                                {name}
                            </span>
                            <span className="ml-auto shrink-0 text-muted-foreground tabular-nums">
                                {row.count.toLocaleString()}
                                <span className="mx-1">·</span>
                                {shareFormatter.format(share)}
                            </span>
                        </li>
                    );
                })}
            </ul>
        </div>
    );
}
