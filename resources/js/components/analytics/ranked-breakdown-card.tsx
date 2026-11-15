import type { LucideIcon } from 'lucide-react';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';

export type BreakdownRow = {
    name: string;
    count: number;
};

export function RankedBreakdownCard({
    title,
    description,
    icon: Icon,
    rows,
    emptyTitle = 'No data in this window',
    emptyDescription = 'Choose another window or check again after consented visits arrive.',
}: {
    title: string;
    description: string;
    icon: LucideIcon;
    rows: readonly BreakdownRow[];
    emptyTitle?: string;
    emptyDescription?: string;
}) {
    const max = Math.max(...rows.map((row) => row.count), 1);

    return (
        <Card className="min-w-0">
            <CardHeader className="gap-1.5">
                <CardTitle
                    as="h2"
                    className="flex items-center gap-2 text-base"
                >
                    <Icon
                        className="size-4 shrink-0 text-muted-foreground"
                        aria-hidden="true"
                    />
                    {title}
                </CardTitle>
                <CardDescription className="text-xs">
                    {description}
                </CardDescription>
            </CardHeader>
            <CardContent>
                {rows.length === 0 ? (
                    <Empty>
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <Icon aria-hidden="true" />
                            </EmptyMedia>
                            <EmptyTitle>{emptyTitle}</EmptyTitle>
                            <EmptyDescription>
                                {emptyDescription}
                            </EmptyDescription>
                        </EmptyHeader>
                    </Empty>
                ) : (
                    <ol className="grid gap-3">
                        {rows.map((row) => (
                            <li key={row.name} className="grid gap-1.5">
                                <div className="flex items-center justify-between gap-3 text-sm">
                                    <span className="truncate">{row.name}</span>
                                    <span className="shrink-0 font-medium tabular-nums">
                                        {row.count.toLocaleString()}
                                    </span>
                                </div>
                                <div
                                    className="h-1.5 overflow-hidden rounded-full bg-surface-secondary"
                                    aria-hidden="true"
                                >
                                    <div
                                        className="h-full rounded-full"
                                        style={{
                                            width: `${Math.max(
                                                (row.count / max) * 100,
                                                2,
                                            )}%`,
                                            backgroundColor: 'var(--chart-2)',
                                        }}
                                    />
                                </div>
                            </li>
                        ))}
                    </ol>
                )}
            </CardContent>
        </Card>
    );
}
