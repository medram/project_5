import { Head, router, usePage } from '@inertiajs/react';
import { Check, CreditCard, ExternalLink } from 'lucide-react';
import { useRef, useState } from 'react';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
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
import { formatMoney } from '@/lib/money';
import { checkout, portal } from '@/routes/billing';
import { abandon } from '@/routes/billing/checkout';
import { checkout as paypalCheckout, cancel as paypalCancel, refresh as paypalRefresh } from '@/routes/billing/paypal';
import { update as updatePlan } from '@/routes/billing/plan';
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

function formatAmount(plan: BillingPlan, amount: number | null): string {
    if (amount === null) return 'Price unavailable';
    return plan.code === 'free' ? 'Free' : formatMoney(amount, plan.currency);
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
    const [processing, setProcessing] = useState(false);
    const [cancelOpen, setCancelOpen] = useState(false);
    const [abandonOpen, setAbandonOpen] = useState(false);
    const errorRef = useRef<HTMLDivElement>(null);
    const { errors } = usePage().props;
    const billingErrors = Object.entries(errors);

    function focusErrors() {
        requestAnimationFrame(() => errorRef.current?.focus());
    }

    function startStripe(plan: string, interval: 'month' | 'year') {
        router.get(checkout.url({ plan, interval }), {}, {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onError: focusErrors,
            onFinish: () => setProcessing(false),
        });
    }

    function startPayPal(plan: string, interval: 'month' | 'year') {
        router.post(paypalCheckout.url({ plan, interval }), {}, {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onError: focusErrors,
            onFinish: () => setProcessing(false),
        });
    }

    function cancelPayPal() {
        router.post(paypalCancel.url(), {}, {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onSuccess: () => setCancelOpen(false),
            onError: focusErrors,
            onFinish: () => setProcessing(false),
        });
    }
    function refreshPayPal() {
        router.post(paypalRefresh.url(), {}, {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onError: focusErrors,
            onFinish: () => setProcessing(false),
        });
    }

    function abandonCheckout() {
        router.post(abandon.url(), {}, {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onSuccess: () => setAbandonOpen(false),
            onError: focusErrors,
            onFinish: () => setProcessing(false),
        });
    }


    function selectFreePlan() {
        router.patch(
            updatePlan.url(),
            { plan: 'free' },
            { preserveScroll: true, onError: focusErrors },
        );
    }

    const providerManaged = effectiveSource === 'stripe' || effectiveSource === 'paypal';
    const subscriptionMessage = providerSubscription?.provider === 'paypal'
        ? 'Cancel the PayPal subscription before changing plans'
        : 'Manage the paid subscription in the Stripe portal before changing plans';

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
                                    {providerManaged
                                        ? providerSubscription?.baseAmount !=
                                              null &&
                                          providerSubscription.currency
                                            ? `${formatMoney(providerSubscription.baseAmount, providerSubscription.currency)}${providerSubscription.interval === 'year' ? '/yr' : providerSubscription.interval === 'month' ? '/mo' : ''}`
                                            : 'Price unavailable'
                                        : `${override ? 'Catalog price: ' : ''}${formatAmount(effectivePlan, effectivePlan.monthlyPrice)}${effectivePlan.monthlyPrice !== null && effectivePlan.code !== 'free' ? '/mo' : ''}`}
                                </p>
                            </div>

                            {providerSubscription && (
                                <div className="rounded-lg border p-3 text-sm">
                                    <p className="font-medium">
                                        {providerSubscription.provider === 'paypal' ? 'PayPal' : 'Stripe'} status:{' '}
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

                            {billing.portalReady && canManageBilling && providerSubscription?.provider === 'stripe' && (
                                <Button type="button" variant="outline" className="max-sm:min-h-11" onClick={() => router.get(portal.url())}>
                                    <ExternalLink data-icon="inline-start" />
                                    Open Stripe billing portal
                                </Button>
                            )}
                            {canManageBilling && providerSubscription?.provider === 'paypal' && !billing.pendingCheckout && (
                                <Button type="button" variant="outline" className="max-sm:min-h-11" disabled={processing} onClick={refreshPayPal}>
                                    Refresh PayPal status
                                </Button>
                            )}
                            {canManageBilling && providerSubscription?.provider === 'paypal' && providerSubscription.canCancel && (
                                <AlertDialog open={cancelOpen} onOpenChange={setCancelOpen}>
                                    <AlertDialogTrigger asChild>
                                        <Button type="button" variant="outline" className="max-sm:min-h-11" disabled={processing}>Cancel PayPal subscription now</Button>
                                    </AlertDialogTrigger>
                                    <AlertDialogContent>
                                        <AlertDialogHeader>
                                            <AlertDialogTitle>Cancel PayPal subscription?</AlertDialogTitle>
                                            <AlertDialogDescription>
                                                Cancellation takes effect immediately. Your paid subscription access will end once cancellation is confirmed.
                                            </AlertDialogDescription>
                                        </AlertDialogHeader>
                                        {billingErrors.length > 0 && <div ref={errorRef} role="alert" tabIndex={-1} className="text-sm text-destructive">{billingErrors.map(([key, message]) => <p key={key}>{message}</p>)}</div>}
                                        <AlertDialogFooter>
                                            <AlertDialogCancel disabled={processing}>Keep subscription</AlertDialogCancel>
                                            <AlertDialogAction disabled={processing} onClick={(event) => { event.preventDefault(); cancelPayPal(); }}>
                                                {processing ? 'Cancelling…' : 'Cancel subscription'}
                                            </AlertDialogAction>
                                        </AlertDialogFooter>
                                    </AlertDialogContent>
                                </AlertDialog>
                            )}
                            {billingErrors.length > 0 && !cancelOpen && !abandonOpen && (
                                <div ref={errorRef} role="alert" tabIndex={-1} className="text-sm text-destructive">
                                    {billingErrors.map(([key, message]) => <p key={key}>{message}</p>)}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                    {billing.pendingCheckout && (
                        <Card>
                            <CardHeader>
                                <CardTitle>A checkout is in progress</CardTitle>
                                <CardDescription>
                                    Finish or cancel your {billing.pendingCheckout.provider === 'paypal' ? 'PayPal' : 'Stripe'} checkout before starting another.
                                </CardDescription>
                            </CardHeader>
                            {canManageBilling && (
                                <CardContent className="flex flex-wrap gap-2">
                                    {billing.pendingCheckout.resumeUrl && (
                                        <Button asChild variant="outline" className="max-sm:min-h-11">
                                            <a href={billing.pendingCheckout.resumeUrl}>Resume checkout</a>
                                        </Button>
                                    )}
                                    {billing.pendingCheckout.provider === 'paypal' && (
                                        <Button type="button" variant="outline" className="max-sm:min-h-11" disabled={processing} onClick={refreshPayPal}>
                                            Refresh PayPal status
                                        </Button>
                                    )}
                                    <AlertDialog open={abandonOpen} onOpenChange={setAbandonOpen}>
                                        <AlertDialogTrigger asChild>
                                            <Button type="button" variant="outline" className="max-sm:min-h-11" disabled={processing}>Cancel checkout</Button>
                                        </AlertDialogTrigger>
                                        <AlertDialogContent>
                                            <AlertDialogHeader>
                                                <AlertDialogTitle>Cancel checkout?</AlertDialogTitle>
                                                <AlertDialogDescription>
                                                    This will cancel the checkout in progress. You can start a new checkout afterwards.
                                                </AlertDialogDescription>
                                            </AlertDialogHeader>
                                            {billingErrors.length > 0 && <div ref={errorRef} role="alert" tabIndex={-1} className="text-sm text-destructive">{billingErrors.map(([key, message]) => <p key={key}>{message}</p>)}</div>}
                                            <AlertDialogFooter>
                                                <AlertDialogCancel disabled={processing}>Keep checkout</AlertDialogCancel>
                                                <AlertDialogAction disabled={processing} onClick={(event) => { event.preventDefault(); abandonCheckout(); }}>
                                                    {processing ? 'Cancelling…' : 'Cancel checkout'}
                                                </AlertDialogAction>
                                            </AlertDialogFooter>
                                        </AlertDialogContent>
                                    </AlertDialog>
                                </CardContent>
                            )}
                        </Card>
                    )}


                    {!billing.checkoutReady && !billing.paypalReady && canManageBilling && (
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
                                                    {plan.code === 'free'
                                                        ? 'No cost'
                                                        : `${formatAmount(plan, plan.monthlyPrice)}${plan.monthlyPrice === null ? '' : '/mo'}`}
                                                    {billing.annualDisplay &&
                                                        plan.code !== 'free' &&
                                                        ` · ${formatAmount(plan, plan.annualPrice)}${plan.annualPrice === null ? '' : '/yr'}`}
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
                                            !billing.hasProviderSubscription && !billing.pendingCheckout ? (
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    className="max-sm:min-h-11"
                                                    onClick={selectFreePlan}
                                                >
                                                    <Check data-icon="inline-start" />
                                                    Select Free
                                                </Button>
                                            ) : (
                                                <span className="text-sm text-muted-foreground">
                                                    {billing.pendingCheckout
                                                        ? 'Finish or cancel the checkout in progress'
                                                        : billing.hasProviderSubscription
                                                          ? subscriptionMessage
                                                          : 'Only workspace owners can change plans'}
                                                </span>
                                            )
                                        ) : canManageBilling &&
                                          billing.enabled &&
                                          !billing.hasProviderSubscription && !billing.pendingCheckout ? (
                                            <div className="flex flex-wrap gap-2">
                                                {billing.checkoutReady && plan.checkout.month && (
                                                    <Button type="button" className="max-sm:min-h-11" disabled={processing} onClick={() => startStripe(plan.code, 'month')}>
                                                        <CreditCard data-icon="inline-start" />
                                                        Card monthly
                                                    </Button>
                                                )}
                                                {billing.annualDisplay && billing.checkoutReady && plan.checkout.year && (
                                                    <Button type="button" variant="outline" className="max-sm:min-h-11" disabled={processing} onClick={() => startStripe(plan.code, 'year')}>
                                                        <CreditCard data-icon="inline-start" />
                                                        Card yearly
                                                    </Button>
                                                )}
                                                {billing.paypalReady && plan.paypalCheckout.month && (
                                                    <Button type="button" variant="outline" className="max-sm:min-h-11" disabled={processing} onClick={() => startPayPal(plan.code, 'month')}>
                                                        {processing ? 'Opening checkout…' : 'PayPal monthly'}
                                                    </Button>
                                                )}
                                                {billing.annualDisplay && billing.paypalReady && plan.paypalCheckout.year && (
                                                    <Button type="button" variant="outline" className="max-sm:min-h-11" disabled={processing} onClick={() => startPayPal(plan.code, 'year')}>
                                                        {processing ? 'Opening checkout…' : 'PayPal yearly'}
                                                    </Button>
                                                )}
                                                {!((billing.checkoutReady && (plan.checkout.month || (billing.annualDisplay && plan.checkout.year))) ||
                                                    (billing.paypalReady && (plan.paypalCheckout.month || (billing.annualDisplay && plan.paypalCheckout.year)))) && (
                                                    <span className="text-sm text-muted-foreground">Price unavailable</span>
                                                )}
                                            </div>
                                        ) : (
                                            <span className="text-sm text-muted-foreground">
                                                {billing.pendingCheckout
                                                    ? 'Finish or cancel the checkout in progress'
                                                    : billing.hasProviderSubscription
                                                      ? subscriptionMessage
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
