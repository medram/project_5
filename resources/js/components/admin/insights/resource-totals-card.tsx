import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

export function ResourceTotalsCard({
    rows,
}: {
    rows: Array<[string, string]>;
}) {
    return (
        <Card className="min-w-0">
            <CardHeader className="gap-1">
                <div className="flex items-baseline justify-between gap-3">
                    <CardTitle as="h2">Resource totals</CardTitle>
                    <span className="text-xs text-muted-foreground tabular-nums">
                        {rows.length} resources
                    </span>
                </div>
                <CardDescription>
                    Current totals across all workspaces, not scoped to the
                    selected window.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <dl className="grid grid-cols-2 gap-x-4">
                    {rows.map(([label, value]) => (
                        <div
                            key={label}
                            className="flex items-baseline justify-between gap-2 border-b border-separator py-2"
                        >
                            <dt className="min-w-0 truncate text-xs text-muted-foreground">
                                {label}
                            </dt>
                            <dd className="shrink-0 text-sm font-medium tabular-nums">
                                {value}
                            </dd>
                        </div>
                    ))}
                </dl>
            </CardContent>
        </Card>
    );
}
