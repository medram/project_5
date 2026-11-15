import { Head, router } from '@inertiajs/react';
import { Check, CreditCard, ExternalLink } from 'lucide-react';
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
    PlanLimits,
} from '@/types/billing';
import {
    limitLabel,
    metricIcons,
    UsageRow,
    usageOrder,
} from '@/components/billing/usage-metrics';

const planFeatureRows: Array<{
    key: keyof PlanLimits;
    singular: string;
    plural: string;
}> = [
    { key: 'links', singular: 'short link', plural: 'short links' },
    { key: 'link_pages', singular: 'link page', plural: 'link pages' },
    { key: 'domains', singular: 'custom domain', plural: 'custom domains' },
    {
        key: 'storage_bytes',
        singular: 'of retained media',
        plural: 'of retained media',
    },
];

function formatAmount(plan: BillingPlan, amount: number): string {
    if (amount === 0) {
        return 'Free';
    }

    const value = amount / 100;

    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: plan.currency,
        maximumFractionDigits: value % 1 === 0 ? 0 : 2,
    }).format(value);
}

export default function BillingIndex({
    effectivePlan,
    effectiveSource,
    override,
    providerSubscription,
    plans,
    usage,
    canManageBilling,
    billing,
}: BillingPageProps) {
    function selectFreePlan() {
        router.patch(
            '/account-settings/billing/plan',
            { plan: 'free' },
            { preserveScroll: true },
        );
    }

    const providerManaged = effectiveSource === 'stripe';

    return (
        <>
            <Head title="Billing & usage" />

            <div className="mx-auto flex w-full max-w-7xl min-w-0 flex-col gap-5 px-5 pt-4 pb-10">
                <PageHeader
                    title="Plan and usage"
                    description="Your workspace plan, its limits, and what the current billing period includes."
                />

                <div className="grid gap-4">
                    <Card>
                        <CardHeader>
                            <CardTitle>Current plan</CardTitle>
                            <CardDescription>
                                Active workspace entitlement
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="flex items-baseline justify-between gap-4">
                                <p className="text-xl font-semibold">
                                    {effectivePlan.name}
                                </p>
                                <p className="text-sm tabular-nums">
                                    {formatAmount(
                                        effectivePlan,
                                        effectivePlan.monthlyPrice,
                                    )}
                                    {effectivePlan.monthlyPrice > 0 && (
                                        <span className="text-muted-foreground">
                                            /mo
                                        </span>
                                    )}
                                </p>
                            </div>

                            {providerSubscription && (
                                <div className="rounded-lg border p-3 text-sm">
                                    <p className="font-medium">
                                        Stripe status:{' '}
                                        {providerSubscription.status}
                                    </p>
                                    {providerSubscription.interval && (
                                        <p className="text-muted-foreground">
                                            Billing interval:{' '}
                                            {providerSubscription.interval ===
                                            'year'
                                                ? 'Yearly'
                                                : 'Monthly'}
                                        </p>
                                    )}
                                    {providerSubscription.endsAt && (
                                        <p className="text-muted-foreground">
                                            Access through{' '}
                                            {new Date(
                                                providerSubscription.endsAt,
                                            ).toLocaleDateString()}
                                        </p>
                                    )}
                                </div>
                            )}

                            {override && (
                                <p className="text-sm text-muted-foreground">
                                    {override.reason}
                                    {override.expiresAt &&
                                        ` · expires ${new Date(
                                            override.expiresAt,
                                        ).toLocaleDateString()}`}
                                </p>
                            )}

                            {billing.portalReady && canManageBilling && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() =>
                                        router.get('/billing/portal')
                                    }
                                >
                                    <ExternalLink data-icon="inline-start" />
                                    Open Stripe billing portal
                                </Button>
                            )}
                        </CardContent>
                    </Card>

                    {!billing.checkoutReady && canManageBilling && (
                        <div className="rounded-xl border bg-card px-5 py-4">
                            <p className="text-sm">
                                Checkout is not available yet
                            </p>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Payments have not been configured for this
                                environment. Existing limits still apply.
                            </p>
                        </div>
                    )}

                    <Card>
                        <CardHeader>
                            <CardTitle>Usage</CardTitle>
                            <CardDescription>
                                Downgrades keep existing resources; only new
                                over-limit writes are blocked.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-3 md:grid-cols-2">
                            {usageOrder.map(({ key, noun }) => (
                                <UsageRow
                                    key={key}
                                    metric={usage[key]}
                                    noun={noun}
                                    metricKey={key}
                                />
                            ))}
                        </CardContent>
                    </Card>

                    <div className="grid gap-4 xl:grid-cols-3">
                        {plans.map((plan) => {
                            const active = plan.code === effectivePlan.code;

                            return (
                                <Card key={plan.code} className="flex flex-col">
                                    <CardHeader>
                                        <div className="flex items-start justify-between gap-3">
                                            <div>
                                                <CardTitle>
                                                    {plan.name}
                                                </CardTitle>
                                                <CardDescription className="text-xs tabular-nums">
                                                    {plan.monthlyPrice === 0
                                                        ? 'No cost'
                                                        : `${formatAmount(
                                                              plan,
                                                              plan.monthlyPrice,
                                                          )}/mo`}
                                                    {plan.annualPrice > 0 &&
                                                        ` · ${formatAmount(
                                                            plan,
                                                            plan.annualPrice,
                                                        )}/yr`}
                                                </CardDescription>
                                            </div>
                                            {active && (
                                                <span className="text-xs text-muted-foreground">
                                                    Current plan
                                                </span>
                                            )}
                                        </div>
                                    </CardHeader>
                                    <CardContent className="flex-1 space-y-2 text-sm">
                                        {plan.description && (
                                            <p className="text-muted-foreground">
                                                {plan.description}
                                            </p>
                                        )}
                                        {planFeatureRows.map(
                                            ({ key, singular, plural }) => {
                                                const limit = plan.limits[key];
                                                const Icon = metricIcons[key];

                                                return (
                                                    <p
                                                        key={key}
                                                        className="flex items-center gap-2"
                                                    >
                                                        <Icon
                                                            className="size-4 shrink-0 text-muted-foreground"
                                                            aria-hidden="true"
                                                        />
                                                        <span className="font-medium tabular-nums">
                                                            {limitLabel(
                                                                key,
                                                                limit,
                                                            )}
                                                        </span>{' '}
                                                        {limit === 1
                                                            ? singular
                                                            : plural}
                                                    </p>
                                                );
                                            },
                                        )}
                                    </CardContent>
                                    <CardFooter className="flex flex-wrap gap-2">
                                        {active ? (
                                            <span className="flex items-center gap-2 text-sm text-muted-foreground">
                                                <Check
                                                    className="size-4"
                                                    aria-hidden="true"
                                                />
                                                Active plan
                                            </span>
                                        ) : plan.code === 'free' ? (
                                            canManageBilling &&
                                            !providerManaged ? (
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    onClick={selectFreePlan}
                                                >
                                                    <Check data-icon="inline-start" />
                                                    Select Free
                                                </Button>
                                            ) : (
                                                <span className="text-sm text-muted-foreground">
                                                    {providerManaged
                                                        ? 'Manage the paid subscription in the portal'
                                                        : 'Only workspace owners can change plans'}
                                                </span>
                                            )
                                        ) : canManageBilling &&
                                          billing.enabled &&
                                          billing.checkoutReady &&
                                          !providerManaged ? (
                                            <div className="flex flex-wrap gap-2">
                                                {plan.checkout.month && (
                                                    <Button
                                                        type="button"
                                                        onClick={() =>
                                                            router.get(
                                                                `/billing/checkout/${plan.code}/month`,
                                                            )
                                                        }
                                                    >
                                                        <CreditCard data-icon="inline-start" />
                                                        Choose monthly
                                                    </Button>
                                                )}
                                                {billing.annualDisplay &&
                                                    plan.checkout.year && (
                                                        <Button
                                                            type="button"
                                                            variant="outline"
                                                            onClick={() =>
                                                                router.get(
                                                                    `/billing/checkout/${plan.code}/year`,
                                                                )
                                                            }
                                                        >
                                                            <CreditCard data-icon="inline-start" />
                                                            Choose yearly
                                                        </Button>
                                                    )}
                                            </div>
                                        ) : (
                                            <span className="text-sm text-muted-foreground">
                                                {providerManaged
                                                    ? 'Manage the paid subscription in the portal'
                                                    : canManageBilling
                                                      ? 'Checkout is not configured'
                                                      : 'Only workspace owners can change plans'}
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
