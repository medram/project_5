import { Activity } from 'lucide-react';
import { useState } from 'react';
import { InsightDailyChart } from '@/components/admin/insights/daily-chart';
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
import { SegmentedControl } from '@/components/ui/segmented-control';

export type ActivityMetricOption = {
    value: string;
    label: string;
    shortLabel?: string;
    total: number;
    emptyLabel?: string;
};

export function InsightActivityCard({
    title = 'Daily activity',
    description,
    daily,
    options,
    days,
    defaultMetric,
}: {
    title?: string;
    description: string;
    daily: Array<Record<string, string | number>>;
    options: ActivityMetricOption[];
    days: number;
    defaultMetric?: string;
}) {
    const [metric, setMetric] = useState(
        defaultMetric ?? options[0]?.value ?? '',
    );
    const fallback = options[0];

    if (!fallback) {
        return null;
    }

    const selected =
        options.find((option) => option.value === metric) ?? fallback;
    const total = selected.total;
    const peak = Math.max(
        ...daily.map((row) => Number(row[selected.value] ?? 0)),
        0,
    );
    const emptyLabel = selected.emptyLabel ?? selected.label.toLowerCase();
    const series = [
        { key: selected.value, label: selected.label, color: 'var(--chart-2)' },
    ];

    return (
        <Card className="min-w-0">
            <CardHeader className="gap-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div className="grid gap-1">
                        <CardTitle as="h2">{title}</CardTitle>
                        <CardDescription>{description}</CardDescription>
                    </div>
                    <SegmentedControl
                        value={selected.value}
                        onValueChange={setMetric}
                        ariaLabel="Activity metric"
                        options={options.map((option) => ({
                            value: option.value,
                            label: option.shortLabel ?? option.label,
                        }))}
                    />
                </div>
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div className="grid gap-0.5">
                        <span className="text-2xl leading-7 font-semibold tracking-[-0.02em] tabular-nums">
                            {total.toLocaleString()}
                        </span>
                        <span className="text-xs text-muted-foreground">
                            {selected.label} · last {days} days
                        </span>
                    </div>
                    <span className="pb-0.5 text-xs text-muted-foreground tabular-nums">
                        Peak {peak.toLocaleString()} in a day
                    </span>
                </div>
            </CardHeader>
            <CardContent className="min-w-0">
                {total === 0 ? (
                    <Empty className="h-[300px]">
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <Activity aria-hidden="true" />
                            </EmptyMedia>
                            <EmptyTitle>
                                No {emptyLabel} in this window
                            </EmptyTitle>
                            <EmptyDescription>
                                Choose a wider window or check again after
                                activity arrives.
                            </EmptyDescription>
                        </EmptyHeader>
                    </Empty>
                ) : (
                    <InsightDailyChart
                        data={daily}
                        series={series}
                        tall
                        label={`${selected.label} per UTC day for the last ${days} days. Window total ${total.toLocaleString()}, daily peak ${peak.toLocaleString()}.`}
                    />
                )}
            </CardContent>
        </Card>
    );
}
