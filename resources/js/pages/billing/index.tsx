import { Head, router } from '@inertiajs/react';
import { Check, CreditCard, Gauge, Infinity } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
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
            '/billing/plan',
            { plan: 'free' },
            { preserveScroll: true },
        );
    }

    return (
        <>
            <Head title="Billing & usage" />

            <div className="flex flex-col gap-6 p-4 sm:p-6">
                <header className="flex flex-col gap-2">
                    <div className="flex items-center gap-2 text-muted-foreground">
                        <CreditCard className="size-4" aria-hidden="true" />
                        <span className="text-sm font-medium">
                            Account billing
                        </span>
                    </div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Plan and usage
                    </h1>
                    <p className="max-w-2xl text-sm text-muted-foreground">
                        Plans control LinkPilot account limits. Payment
                        collection is not configured in this local environment.
                    </p>
                </header>

                <div className="grid gap-4 lg:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]">
                    <Card>
                        <CardHeader>
                            <div className="flex items-start justify-between gap-3">
                                <div>
                                    <CardTitle>Current plan</CardTitle>
                                    <CardDescription>
                                        Your active local entitlement.
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

                    <div className="grid gap-4 md:grid-cols-3">
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
                                            <Button
                                                type="button"
                                                variant="outline"
                                                onClick={selectFreePlan}
                                            >
                                                Select Free
                                            </Button>
                                        ) : (
                                            <span className="flex items-center gap-2 text-sm text-muted-foreground">
                                                <Infinity
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
