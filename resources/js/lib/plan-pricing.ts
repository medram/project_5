import type { AdminPlanRecord } from '@/types/admin-billing';

export type PlanPriceInterval = 'month' | 'year';

export type AdminPlanPrice = AdminPlanRecord['prices'][number];

/**
 * Resolve the price row currently mapped to a plan interval. History rows for
 * older Stripe prices stay in `plan.prices` but are not current.
 */
export function currentPlanPrice(
    plan: AdminPlanRecord,
    interval: PlanPriceInterval,
): AdminPlanPrice | null {
    const id =
        interval === 'month'
            ? plan.stripeMonthlyPriceId
            : plan.stripeYearlyPriceId;

    if (id === null || id === '') {
        return null;
    }

    return (
        plan.prices.find(
            (price) => price.id === id && price.interval === interval,
        ) ?? null
    );
}

export function currentPlanPriceIsVerified(
    plan: AdminPlanRecord,
    interval: PlanPriceInterval,
): boolean {
    const price = currentPlanPrice(plan, interval);

    return price !== null && price.verified && price.amount !== null;
}
