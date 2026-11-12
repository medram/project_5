import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { AdminDimension } from '@/types/admin-insights';

export function DimensionPanel({
    title,
    description,
    rows,
    threshold,
}: {
    title: string;
    description: string;
    rows: AdminDimension[];
    threshold: number;
}) {
    return (
        <Card>
            <CardHeader className="gap-1">
                <CardTitle className="text-base">{title}</CardTitle>
                <CardDescription>
                    {description} Buckets under {threshold} events are grouped
                    as Other.
                </CardDescription>
            </CardHeader>
            <CardContent>
                {rows.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        No consenting activity in this window.
                    </p>
                ) : (
                    <ul className="divide-y rounded-md border">
                        {rows.map((row) => (
                            <li
                                key={row.name}
                                className="flex items-center justify-between gap-3 px-3 py-2 text-sm"
                            >
                                <span className="min-w-0 truncate">
                                    {row.name}
                                </span>
                                <span className="shrink-0 tabular-nums">
                                    {row.count.toLocaleString()}
                                </span>
                            </li>
                        ))}
                    </ul>
                )}
            </CardContent>
        </Card>
    );
}
