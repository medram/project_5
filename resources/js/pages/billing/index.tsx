import { Head, router } from '@inertiajs/react';
import {
    Check,
    CreditCard,
    Gauge,
    Infinity as InfinityIcon,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type {
    BillingPageProps,
    BillingPlan,
    UsageMetric,
} from '@/types/billing';

function priceFor(plan: BillingPlan): string {
    if (plan.monthlyPrice === 0) {
        return 'Free';
    }

    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: plan.currency,
    }).format(plan.monthlyPrice / 100);
}

function limitLabel(value: number | null): string {
    return value === null ? 'Unlimited' : value.toLocaleString();
}

function UsageRow({ metric, noun }: { metric: UsageMetric; noun: string }) {
    return (
        <div className="rounded-lg border p-3">
            <div className="flex items-center justify-between gap-4">
                <div className="flex items-center gap-2">
                    <Gauge
                        className="size-4 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <span className="font-medium">{metric.label}</span>
                </div>
                <span className="text-sm text-muted-foreground">
                    {metric.used.toLocaleString()} / {limitLabel(metric.limit)}
                </span>
            </div>
            {metric.overLimit && (
                <p className="mt-2 text-sm text-destructive">
                    New {noun} are blocked until usage is back within this
                    plan’s limit.
                </p>
            )}
        </div>
    );
}

export default function BillingIndex({
    subscription,
    plans,
    usage,
    canManageBilling,
}: BillingPageProps) {
    const usageMetrics: { metric: UsageMetric; noun: string }[] = [
        { metric: usage.links, noun: 'links' },
        { metric: usage.domains, noun: 'domains' },
        { metric: usage.pixels, noun: 'pixels' },
        { metric: usage.linkPages, noun: 'link pages' },
        { metric: usage.webhooks, noun: 'webhooks' },
    ];

    function selectFreePlan() {
        router.patch(
            '/account-settings/billing/plan',
            { plan: 'free' },
            { preserveScroll: true },
        );
    }

    return (
        <>
            <Head title="Billing & usage" />

            <div className="flex flex-col gap-6 p-4 sm:p-6">
                <PageHeader
                    title="Plan and usage"
                    eyebrow="Workspace billing"
                    icon={CreditCard}
                    description="Plans control the current workspace limits. Payment collection is not configured in this local environment."
                />

                <div className="grid gap-4">
                    <Card>
                        <CardHeader>
                            <div className="flex items-start justify-between gap-3">
                                <div>
                                    <CardTitle>Current plan</CardTitle>
                                    <CardDescription>
                                        Limits apply to the active workspace.
                                    </CardDescription>
                                </div>
                                <Badge variant="secondary">
                                    {subscription.status}
                                </Badge>
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div>
                                <p className="text-2xl font-semibold">
                                    {subscription.plan.name}
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    {priceFor(subscription.plan)} / month
                                </p>
                            </div>
                            {usageMetrics.map(({ metric, noun }) => (
                                <UsageRow
                                    key={noun}
                                    metric={metric}
                                    noun={noun}
                                />
                            ))}
                        </CardContent>
                    </Card>

                    <div className="grid gap-4 xl:grid-cols-3">
                        {plans.map((plan) => {
                            const active = plan.code === subscription.plan.code;

                            return (
                                <Card key={plan.code} className="flex flex-col">
                                    <CardHeader>
                                        <div className="flex items-start justify-between gap-3">
                                            <div>
                                                <CardTitle>
                                                    {plan.name}
                                                </CardTitle>
                                                <CardDescription>
                                                    {priceFor(plan)} / month
                                                </CardDescription>
                                            </div>
                                            {active && <Badge>Current</Badge>}
                                        </div>
                                    </CardHeader>
                                    <CardContent className="flex-1 space-y-2 text-sm">
                                        <p>
                                            <span className="font-medium">
                                                {limitLabel(plan.limits.links)}
                                            </span>{' '}
                                            short links
                                        </p>
                                        <p>
                                            <span className="font-medium">
                                                {limitLabel(
                                                    plan.limits.linkPages,
                                                )}
                                            </span>{' '}
                                            link pages
                                        </p>
                                        <p>
                                            <span className="font-medium">
                                                {limitLabel(
                                                    plan.limits.domains,
                                                )}
                                            </span>{' '}
                                            custom domains
                                        </p>
                                    </CardContent>
                                    <CardFooter>
                                        {active ? (
                                            <span className="flex items-center gap-2 text-sm text-muted-foreground">
                                                <Check
                                                    className="size-4"
                                                    aria-hidden="true"
                                                />
                                                Active plan
                                            </span>
                                        ) : plan.code === 'free' ? (
                                            canManageBilling ? (
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    onClick={selectFreePlan}
                                                >
                                                    Select Free
                                                </Button>
                                            ) : (
                                                <span className="text-sm text-muted-foreground">
                                                    Only workspace owners can
                                                    change plans
                                                </span>
                                            )
                                        ) : (
                                            <span className="flex items-center gap-2 text-sm text-muted-foreground">
                                                <InfinityIcon
                                                    className="size-4"
                                                    aria-hidden="true"
                                                />
                                                Assigned by an administrator
                                            </span>
                                        )}
                                    </CardFooter>
                                </Card>
                            );
                        })}
                    </div>
                </div>
            </div>
        </>
    );
}
