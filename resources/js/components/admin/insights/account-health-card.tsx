import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import type { AdminPlatformInsights } from '@/types/admin-insights';

const shareFormatter = new Intl.NumberFormat(undefined, {
    style: 'percent',
    maximumFractionDigits: 0,
});

export function AccountHealthCard({
    accounts,
    subscriptions,
    activeSubscriptions,
}: {
    accounts: AdminPlatformInsights['accounts'];
    subscriptions: AdminPlatformInsights['subscriptions'];
    activeSubscriptions: number;
}) {
    const total = accounts.total;
    const activeShare = total > 0 ? accounts.active / total : 0;
    const mappedTotal =
        subscriptions.mappedSubscriptions + subscriptions.unmappedSubscriptions;

    return (
        <Card className="min-w-0">
            <CardHeader className="gap-1">
                <div className="flex items-baseline justify-between gap-3">
                    <CardTitle as="h2">Account health</CardTitle>
                    <span className="text-xs text-muted-foreground">
                        Snapshot
                    </span>
                </div>
                <CardDescription>
                    Lifecycle state and billing mapping across all accounts.
                </CardDescription>
            </CardHeader>
            <CardContent className="grid gap-4">
                <div className="grid gap-2">
                    <div className="flex items-baseline justify-between gap-3">
                        <p className="text-sm">
                            <span className="text-2xl leading-7 font-semibold tracking-[-0.02em] tabular-nums">
                                {accounts.active}
                            </span>
                            <span className="text-muted-foreground">
                                {' '}
                                of {total} accounts active
                            </span>
                        </p>
                        {total > 0 ? (
                            <span className="shrink-0 text-xs text-muted-foreground tabular-nums">
                                {shareFormatter.format(activeShare)}
                            </span>
                        ) : null}
                    </div>
                    <div
                        role="progressbar"
                        aria-label="Active accounts"
                        aria-valuemin={0}
                        aria-valuemax={total}
                        aria-valuenow={accounts.active}
                        aria-valuetext={`${accounts.active} of ${total} accounts active`}
                        className="h-1.5 overflow-hidden rounded-full bg-surface-secondary"
                    >
                        <div
                            className="h-full rounded-full bg-chart-2"
                            style={{ width: `${activeShare * 100}%` }}
                        />
                    </div>
                </div>

                <dl className="grid grid-cols-2 gap-3">
                    <div className="grid gap-0.5">
                        <dt className="flex items-center gap-1.5 text-xs text-muted-foreground">
                            <span
                                className="size-1.5 rounded-full bg-warning"
                                aria-hidden="true"
                            />
                            Suspended
                        </dt>
                        <dd className="text-base font-medium tabular-nums">
                            {accounts.suspended.toLocaleString()}
                        </dd>
                    </div>
                    <div className="grid gap-0.5">
                        <dt className="flex items-center gap-1.5 text-xs text-muted-foreground">
                            <span
                                className="size-1.5 rounded-full bg-danger"
                                aria-hidden="true"
                            />
                            Deletion pending
                        </dt>
                        <dd className="text-base font-medium tabular-nums">
                            {accounts.deletionPending.toLocaleString()}
                        </dd>
                    </div>
                </dl>

                <Separator />

                <div className="grid gap-2 text-sm">
                    <div className="flex items-baseline justify-between gap-3">
                        <span className="text-muted-foreground">
                            Active subscriptions
                        </span>
                        <span className="font-medium tabular-nums">
                            {activeSubscriptions.toLocaleString()}
                        </span>
                    </div>
                    <div className="flex items-baseline justify-between gap-3">
                        <span className="text-muted-foreground">
                            Revenue mapping
                        </span>
                        <span
                            className={
                                mappedTotal === 0
                                    ? 'text-xs text-muted-foreground'
                                    : 'font-medium tabular-nums'
                            }
                        >
                            {mappedTotal === 0
                                ? 'No subscriptions to map'
                                : `${subscriptions.mappedSubscriptions.toLocaleString()} mapped · ${subscriptions.unmappedSubscriptions.toLocaleString()} unmapped`}
                        </span>
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}
