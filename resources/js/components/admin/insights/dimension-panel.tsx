import type { LucideIcon } from 'lucide-react';
import { DimensionDonut } from '@/components/admin/insights/donut-chart';
import { RankedBars } from '@/components/admin/insights/ranked-bars';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { AdminDimension } from '@/types/admin-insights';

type DimensionPanelProps = {
    title: string;
    description: string;
    rows: AdminDimension[];
    threshold?: number;
    visual?: 'bars' | 'donut';
    nameFormatter?: (name: string) => string;
    footer?: string;
    emptyMessage?: string;
    totalLabel?: string;
    cardVariant?: 'default' | 'transparent';
    icon?: LucideIcon;
};

export function DimensionPanel({
    title,
    description,
    rows,
    threshold,
    visual = 'bars',
    nameFormatter,
    footer,
    emptyMessage,
    totalLabel,
    cardVariant = 'default',
    icon: Icon,
}: DimensionPanelProps) {
    return (
        <Card variant={cardVariant} className="min-w-0">
            <CardHeader className="gap-1">
                <CardTitle
                    className="flex items-center gap-2 text-base"
                    as="h2"
                >
                    {Icon ? (
                        <Icon
                            className="size-4 shrink-0 text-muted-foreground"
                            aria-hidden="true"
                        />
                    ) : null}
                    {title}
                </CardTitle>
                <CardDescription>
                    {description}
                    {threshold === undefined
                        ? ''
                        : ` Buckets under ${threshold} events are grouped as Other.`}
                </CardDescription>
            </CardHeader>
            <CardContent>
                {rows.length === 0 ? (
                    <p className="rounded-lg border border-dashed border-border px-4 py-5 text-center text-sm text-muted-foreground">
                        {emptyMessage ??
                            'No consenting activity in this window.'}
                    </p>
                ) : visual === 'donut' ? (
                    <DimensionDonut
                        rows={rows}
                        label={title}
                        nameFormatter={nameFormatter}
                        totalLabel={totalLabel ?? 'events'}
                    />
                ) : (
                    <RankedBars rows={rows} nameFormatter={nameFormatter} />
                )}
                {footer ? (
                    <p className="mt-3 text-xs text-muted-foreground">
                        {footer}
                    </p>
                ) : null}
            </CardContent>
        </Card>
    );
}
