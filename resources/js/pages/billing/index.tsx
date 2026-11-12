import { Head, router } from '@inertiajs/react';
import { Check, CreditCard, Gauge, ShieldAlert } from 'lucide-react';
import { PageHeader } from '@/components/page-header';
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
    PlanLimits,
    UsageMetric,
} from '@/types/billing';

const usageOrder: Array<{ key: keyof PlanLimits; noun: string }> = [
    { key: 'links', noun: 'links' },
    { key: 'folders', noun: 'folders' },
    { key: 'qr_campaigns', noun: 'direct QR campaigns' },
    { key: 'bio_pages', noun: 'bio pages' },
    { key: 'domains', noun: 'custom domains' },
    { key: 'pixels', noun: 'tracking pixels' },
    { key: 'members', noun: 'members' },
    { key: 'webhooks', noun: 'webhooks' },
    { key: 'link_pages', noun: 'link pages' },
    { key: 'api_keys', noun: 'active API keys' },
    { key: 'storage_bytes', noun: 'retained media bytes' },
];

function formatAmount(plan: BillingPlan, amount: number): string {
    if (amount === 0) {
        return 'Free';
    }

    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: plan.currency,
    }).format(amount / 100);
}

function limitLabel(value: number | null): string {
    return value === null ? 'Unlimited' : value.toLocaleString();
}

function sourceLabel(source: string): string {
    return (
        {
            stripe: 'Stripe subscription',
            free: 'Free plan',
            support_grant: 'Support override',
            promotion: 'Promotion override',
            manual: 'Manual override',
            legacy_manual: 'Legacy override',
        }[source] ?? source
    );
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

            <div className="flex flex-col gap-6 p-4 sm:p-6">
                <PageHeader
                    title="Plan and usage"
                    eyebrow="Workspace billing"
                    icon={CreditCard}
                    description="Stripe is authoritative for paid subscriptions. Missing provider configuration disables checkout instead of pretending success."
                />

                <div className="grid gap-4">
                    <Card>
                        <CardHeader>
                            <div className="flex items-start justify-between gap-3">
                                <div>
                                    <CardTitle>Current plan</CardTitle>
                                    <CardDescription>
                                        Entitlement for the active workspace.
                                    </CardDescription>
                                </div>
                                <Badge variant="secondary">
                                    {sourceLabel(effectiveSource)}
                                </Badge>
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div>
                                <p className="text-2xl font-semibold">
                                    {effectivePlan.name}
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    {formatAmount(
                                        effectivePlan,
                                        effectivePlan.monthlyPrice,
                                    )}{' '}
                                    / month
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
                                <div className="rounded-lg border border-dashed p-3 text-sm">
                                    <p className="font-medium">
                                        {sourceLabel(override.source)}
                                    </p>
                                    <p className="text-muted-foreground">
                                        {override.reason}
                                    </p>
                                    {override.expiresAt && (
                                        <p className="text-muted-foreground">
                                            Expires{' '}
                                            {new Date(
                                                override.expiresAt,
                                            ).toLocaleDateString()}
                                        </p>
                                    )}
                                </div>
                            )}

                            {billing.portalReady && canManageBilling && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() =>
                                        router.get('/billing/portal')
                                    }
                                >
                                    Open Stripe billing portal
                                </Button>
                            )}
                        </CardContent>
                    </Card>

                    {!billing.checkoutReady && canManageBilling && (
                        <Card className="border-dashed">
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <ShieldAlert
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    Billing setup required
                                </CardTitle>
                                <CardDescription>
                                    Checkout stays disabled until every operator
                                    prerequisite is configured.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <ul className="list-inside list-disc text-sm">
                                    {billing.missing.map((item) => (
                                        <li key={item}>{item}</li>
                                    ))}
                                </ul>
                                {providerSubscription === null &&
                                    effectiveSource !== 'stripe' && (
                                        <p className="mt-3 text-sm text-muted-foreground">
                                            No paid subscription is active for
                                            this workspace.
                                        </p>
                                    )}
                            </CardContent>
                        </Card>
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
                                                <CardDescription>
                                                    {formatAmount(
                                                        plan,
                                                        plan.monthlyPrice,
                                                    )}{' '}
                                                    / month ·{' '}
                                                    {formatAmount(
                                                        plan,
                                                        plan.annualPrice,
                                                    )}{' '}
                                                    / year
                                                </CardDescription>
                                            </div>
                                            {active && <Badge>Current</Badge>}
                                        </div>
                                    </CardHeader>
                                    <CardContent className="flex-1 space-y-2 text-sm">
                                        {plan.description && (
                                            <p className="text-muted-foreground">
                                                {plan.description}
                                            </p>
                                        )}
                                        <p>
                                            <span className="font-medium">
                                                {limitLabel(plan.limits.links)}
                                            </span>{' '}
                                            short links
                                        </p>
                                        <p>
                                            <span className="font-medium">
                                                {limitLabel(
                                                    plan.limits.link_pages,
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
                                        <p>
                                            <span className="font-medium">
                                                {limitLabel(
                                                    plan.limits.storage_bytes,
                                                )}
                                            </span>{' '}
                                            retained media bytes
                                        </p>
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
                                                        Choose monthly
                                                    </Button>
                                                )}
                                                {plan.checkout.year && (
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        onClick={() =>
                                                            router.get(
                                                                `/billing/checkout/${plan.code}/year`,
                                                            )
                                                        }
                                                    >
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
