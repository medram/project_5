import { Head, Link } from '@inertiajs/react';
import { CheckCircle2, Clock, XCircle } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { index as billingIndex } from '@/routes/billing';
import type { BillingPlan } from '@/types/billing';

type Props = {
    status: 'success' | 'cancelled';
    checkoutProvider: 'stripe' | 'paypal';
    processing: boolean;
    sessionReturned: boolean;
    effectivePlan: BillingPlan;
    effectiveSource: string;
    provider: {
        status: string;
        endsAt: string | null;
    } | null;
};

export default function CheckoutResult({
    status,
    checkoutProvider,
    processing,
    sessionReturned,
    effectivePlan,
    effectiveSource,
    provider,
}: Props) {
    const active = status === 'success' && !processing && effectiveSource === checkoutProvider;
    const providerName = checkoutProvider === 'paypal' ? 'PayPal' : 'Stripe';

    return (
        <>
            <Head
                title={
                    status === 'cancelled'
                        ? 'Checkout cancelled'
                        : 'Checkout received'
                }
            />

            <div className="mx-auto flex w-full max-w-xl flex-col gap-6 p-4 sm:p-6">
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            {status === 'cancelled' ? (
                                <XCircle
                                    className="size-5 text-muted-foreground"
                                    aria-hidden="true"
                                />
                            ) : active ? (
                                <CheckCircle2
                                    className="size-5 text-success-soft-foreground"
                                    aria-hidden="true"
                                />
                            ) : (
                                <Clock
                                    className="size-5 text-warning"
                                    aria-hidden="true"
                                />
                            )}
                            {status === 'cancelled'
                                ? 'Checkout cancelled'
                                : active
                                  ? 'Subscription active'
                                  : 'Subscription confirmation pending'}
                        </CardTitle>
                        <CardDescription>
                            {status === 'cancelled'
                                ? 'Checkout was cancelled. Check your billing page for the current subscription status.'
                                : active
                                  ? `${providerName} confirmed the subscription and the workspace entitlement is active.`
                                  : `LinkPilot is waiting for verified ${providerName} subscription confirmation. Approval or a return URL alone does not grant a plan.`}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-3 text-sm">
                        <div className="flex items-center justify-between gap-3">
                            <span className="text-muted-foreground">
                                Effective plan
                            </span>
                            <span className="font-medium">
                                {effectivePlan.name}
                            </span>
                        </div>
                        <div className="flex items-center justify-between gap-3">
                            <span className="text-muted-foreground">
                                Entitlement source
                            </span>
                            <Badge variant="secondary">{effectiveSource}</Badge>
                        </div>
                        {provider && (
                            <div className="flex items-center justify-between gap-3">
                                <span className="text-muted-foreground">
                                    {providerName} status
                                </span>
                                <span className="font-medium">
                                    {provider.status}
                                </span>
                            </div>
                        )}
                        {provider?.endsAt && (
                            <div className="flex items-center justify-between gap-3">
                                <span className="text-muted-foreground">
                                    Access through
                                </span>
                                <span className="font-medium">
                                    {new Date(
                                        provider.endsAt,
                                    ).toLocaleDateString()}
                                </span>
                            </div>
                        )}
                        {sessionReturned && processing && (
                            <p className="text-muted-foreground">
                                You returned from checkout. Refresh after the
                                subscription is confirmed if activation is still
                                pending.
                            </p>
                        )}
                    </CardContent>
                </Card>

                <Button asChild variant="outline">
                    <Link href={billingIndex.url()}>
                        Back to billing
                    </Link>
                </Button>
            </div>
        </>
    );
}
