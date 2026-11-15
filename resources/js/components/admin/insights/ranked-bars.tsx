import type { AdminDimension } from '@/types/admin-insights';

const shareFormatter = new Intl.NumberFormat(undefined, {
    style: 'percent',
    maximumFractionDigits: 1,
});

export function RankedBars({
    rows,
    nameFormatter = (name) => name,
}: {
    rows: AdminDimension[];
    nameFormatter?: (name: string) => string;
}) {
    if (rows.length === 0) {
        return null;
    }

    const max = Math.max(...rows.map((row) => row.count), 1);
    const denominator = rows.reduce((total, row) => total + row.count, 0);
    const leadingName = rows.find((row) => row.name !== 'Other')?.name;

    return (
        <ul data-slot="ranked-bars" className="grid gap-3">
            {rows.map((row) => {
                const name = nameFormatter(row.name);
                const share = denominator > 0 ? row.count / denominator : 0;
                const isLeading = row.name === leadingName;

                return (
                    <li key={row.name} className="grid gap-1.5">
                        <div className="flex items-baseline justify-between gap-3">
                            <span
                                className="min-w-0 truncate text-sm"
                                title={name}
                            >
                                {name}
                            </span>
                            <span className="shrink-0 text-xs text-muted-foreground tabular-nums">
                                {row.count.toLocaleString()}
                                <span className="mx-1">·</span>
                                {shareFormatter.format(share)}
                            </span>
                        </div>
                        <div
                            className="h-1.5 overflow-hidden rounded-[2px] bg-muted"
                            aria-hidden="true"
                        >
                            <div
                                className="rounded-[2px]"
                                style={{
                                    width: `${Math.max((row.count / max) * 100, row.count > 0 ? 2 : 0)}%`,
                                    height: '100%',
                                    background: isLeading
                                        ? 'var(--chart-1)'
                                        : 'var(--chart-2)',
                                }}
                            />
                        </div>
                    </li>
                );
            })}
        </ul>
    );
}
