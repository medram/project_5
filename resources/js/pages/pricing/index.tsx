import { Head, Link } from '@inertiajs/react';
import { Check } from 'lucide-react';
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
import { formatMoney } from '@/lib/money';
import type { BillingPlan } from '@/types/billing';

type Props = {
    plans: BillingPlan[];
    currentPlanCode: string | null;
    canManageBilling: boolean;
    annualDisplay: boolean;
    billingEnabled: boolean;
    billingPath: string;
    loginPath: string;
};

function amount(plan: BillingPlan, value: number | null): string {
    if (value === null) return 'Price unavailable';
    if (plan.code === 'free') return 'Free';
    return formatMoney(value, plan.currency);
}

export default function Pricing({
    plans,
    currentPlanCode,
    canManageBilling,
    annualDisplay,
    billingEnabled,
    billingPath,
    loginPath,
}: Props) {
    return (
        <>
            <Head title="Pricing" />

            <div className="mx-auto flex w-full max-w-6xl flex-col gap-8 p-4 py-10 sm:p-6">
                <header className="space-y-3 text-center">
                    <p className="text-sm font-medium tracking-wide text-muted-foreground uppercase">
                        LinkPilot plans
                    </p>
                    <h1 className="text-3xl font-semibold tracking-tight">
                        Publish, measure, and govern links
                    </h1>
                    <p className="mx-auto max-w-2xl text-sm text-muted-foreground">
                        Every plan applies to one workspace. Limits are enforced
                        before new resources are created; downgrades never
                        delete existing content.
                    </p>
                </header>

                <div className="grid gap-4 md:grid-cols-3">
                    {plans.map((plan) => {
                        const active = plan.code === currentPlanCode;

                        return (
                            <Card
                                key={plan.code}
                                className={
                                    plan.featured ? 'border-primary' : undefined
                                }
                            >
                                <CardHeader>
                                    <div className="flex items-start justify-between gap-2">
                                        <div>
                                            <CardTitle>{plan.name}</CardTitle>
                                            <CardDescription>
                                                {plan.description ??
                                                    'Workspace plan'}
                                            </CardDescription>
                                        </div>
                                        {plan.featured && (
                                            <Badge>Popular</Badge>
                                        )}
                                    </div>
                                </CardHeader>
                                <CardContent className="space-y-4 text-sm">
                                    <div>
                                        <p className="text-2xl font-semibold">
                                            {amount(plan, plan.monthlyPrice)}
                                            {plan.monthlyPrice !== null && (
                                                <span className="text-sm font-normal text-muted-foreground">
                                                    {' '}/ month
                                                </span>
                                            )}
                                        </p>
                                        <p className="text-muted-foreground">
                                            {annualDisplay
                                                ? `${amount(plan, plan.annualPrice)}${plan.annualPrice === null ? '' : ' / year'}`
                                                : 'Annual billing hidden'}
                                        </p>
                                    </div>

                                    <ul className="space-y-1">
                                        <li className="flex items-center gap-2">
                                            <Check
                                                className="size-4"
                                                aria-hidden="true"
                                            />
                                            {plan.limits.links === null
                                                ? 'Unlimited'
                                                : plan.limits.links.toLocaleString()}{' '}
                                            short links
                                        </li>
                                        <li className="flex items-center gap-2">
                                            <Check
                                                className="size-4"
                                                aria-hidden="true"
                                            />
                                            {plan.limits.link_pages === null
                                                ? 'Unlimited'
                                                : plan.limits.link_pages.toLocaleString()}{' '}
                                            link pages
                                        </li>
                                        <li className="flex items-center gap-2">
                                            <Check
                                                className="size-4"
                                                aria-hidden="true"
                                            />
                                            {plan.limits.domains === null
                                                ? 'Unlimited'
                                                : plan.limits.domains.toLocaleString()}{' '}
                                            custom domains
                                        </li>
                                        <li className="flex items-center gap-2">
                                            <Check
                                                className="size-4"
                                                aria-hidden="true"
                                            />
                                            {plan.limits.members === null
                                                ? 'Unlimited'
                                                : plan.limits.members.toLocaleString()}{' '}
                                            workspace members
                                        </li>
                                    </ul>
                                </CardContent>
                                <CardFooter>
                                    {active ? (
                                        <Badge variant="secondary">
                                            Current plan
                                        </Badge>
                                    ) : !billingEnabled ? (
                                        <Badge variant="outline">
                                            Billing unavailable
                                        </Badge>
                                    ) : canManageBilling ? (
                                        <Button asChild variant="outline">
                                            <Link href={billingPath}>
                                                Manage in billing
                                            </Link>
                                        </Button>
                                    ) : (
                                        <Button asChild variant="outline">
                                            <Link href={loginPath}>
                                                Sign in to choose
                                            </Link>
                                        </Button>
                                    )}
                                </CardFooter>
                            </Card>
                        );
                    })}
                </div>

                <p className="text-center text-xs text-muted-foreground">
                    Paid checkout opens only when Stripe is configured. No
                    payment is simulated locally.
                </p>
            </div>
        </>
    );
}
