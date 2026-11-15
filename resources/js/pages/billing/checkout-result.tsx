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
import type { BillingPlan } from '@/types/billing';

type Props = {
    status: 'success' | 'cancelled';
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
    processing,
    sessionReturned,
    effectivePlan,
    effectiveSource,
    provider,
}: Props) {
    const active = status === 'success' && !processing;

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
                                    className="size-5 text-green-600"
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
                                  : 'Payment received — activation pending'}
                        </CardTitle>
                        <CardDescription>
                            {status === 'cancelled'
                                ? 'No payment was taken. Your workspace plan did not change.'
                                : active
                                  ? 'Stripe confirmed the subscription and the workspace entitlement is active.'
                                  : 'LinkPilot is waiting for the signed Stripe webhook. A return URL never grants a plan by itself.'}
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
                                    Stripe status
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
                                Checkout session returned. Refresh after the
                                webhook is processed if activation is still
                                pending.
                            </p>
                        )}
                    </CardContent>
                </Card>

                <Button asChild variant="outline">
                    <Link href="/account-settings/billing">
                        Back to billing
                    </Link>
                </Button>
            </div>
        </>
    );
}
