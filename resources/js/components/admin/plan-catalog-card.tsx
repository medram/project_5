import {
    Globe2,
    HardDrive,
    Link2,
    Pencil,
    QrCode,
    Star,
    Trash2,
    Users,
    type LucideIcon,
} from 'lucide-react';
import { limitLabel } from '@/components/billing/usage-metrics';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { formatMoney } from '@/lib/money';
import {
    currentPlanPrice,
    currentPlanPriceIsVerified,
} from '@/lib/plan-pricing';
import type { AdminPlanRecord } from '@/types/admin-billing';
import type { PlanLimits } from '@/types/billing';

const summaryLimits: Array<{
    key: keyof PlanLimits;
    noun: string;
    icon: LucideIcon;
}> = [
    { key: 'links', noun: 'links', icon: Link2 },
    { key: 'qr_campaigns', noun: 'QR', icon: QrCode },
    { key: 'members', noun: 'members', icon: Users },
    { key: 'domains', noun: 'domains', icon: Globe2 },
    { key: 'storage_bytes', noun: 'storage', icon: HardDrive },
];

function planPrice(plan: AdminPlanRecord) {
    if (plan.code === 'free') {
        return {
            main: formatMoney(0, plan.currency),
            suffix: 'free',
            note: 'No Stripe price',
        };
    }

    const monthly = currentPlanPrice(plan, 'month');
    const annual = currentPlanPrice(plan, 'year');
    const monthlyReady = currentPlanPriceIsVerified(plan, 'month');
    const annualReady = currentPlanPriceIsVerified(plan, 'year');

    if (!plan.stripeMonthlyPriceId && !plan.stripeYearlyPriceId) {
        return {
            main: null,
            suffix: '',
            note: 'Add a monthly or yearly price',
        };
    }

    return {
        main: monthlyReady
            ? formatMoney(monthly?.amount ?? 0, plan.currency)
            : null,
        suffix: '/ month',
        note: monthlyReady
            ? annualReady
                ? `${formatMoney(annual?.amount ?? 0, plan.currency)} / year`
                : 'Annual price not set'
            : 'Price not verified',
    };
}

export function PlanCatalogCard({
    plan,
    canManage,
    onEdit,
    onDelete,
}: {
    plan: AdminPlanRecord;
    canManage: boolean;
    onEdit: () => void;
    onDelete?: () => void;
}) {
    const price = planPrice(plan);
    const checkout =
        plan.code === 'free'
            ? null
            : plan.checkout.month && plan.checkout.year
              ? { label: 'Checkout ready', dot: 'bg-success' }
              : { label: 'Checkout off', dot: 'bg-warning' };

    return (
        <Card className="gap-0 overflow-hidden py-0">
            <div className="flex flex-1 flex-col gap-4 p-4">
                <div className="min-w-0">
                    <div className="flex flex-wrap items-center gap-2">
                        <h2 className="text-lg leading-6 font-semibold tracking-[-0.01em]">
                            {plan.name}
                        </h2>
                        <code className="rounded-md border border-border bg-surface-secondary px-1.5 py-0.5 font-mono text-[11px] leading-4 text-muted-foreground">
                            {plan.code}
                        </code>
                    </div>
                    <p className="mt-1 line-clamp-2 min-h-5 text-sm leading-5 text-muted-foreground">
                        {plan.description ?? 'No description'}
                    </p>
                </div>

                <div>
                    <div className="flex flex-wrap items-baseline gap-x-1.5 gap-y-0.5">
                        {price.main ? (
                            <>
                                <span className="text-2xl leading-7 font-semibold tracking-[-0.02em] tabular-nums">
                                    {price.main}
                                </span>
                                <span className="text-xs text-muted-foreground">
                                    {price.suffix}
                                </span>
                            </>
                        ) : (
                            <span className="text-sm text-muted-foreground">
                                No price set
                            </span>
                        )}
                    </div>
                    <div className="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground">
                        <span>{price.note}</span>
                        {checkout && (
                            <span className="inline-flex items-center gap-1.5">
                                <span
                                    aria-hidden="true"
                                    className={`size-1.5 rounded-full ${checkout.dot}`}
                                />
                                {checkout.label}
                            </span>
                        )}
                    </div>
                </div>

                <div className="flex flex-wrap gap-1.5">
                    {summaryLimits.map(({ key, noun, icon: Icon }) => (
                        <span
                            key={key}
                            className="inline-flex items-center gap-1.5 rounded-lg border border-border bg-surface-secondary px-2 py-1 text-xs text-muted-foreground"
                        >
                            <Icon className="size-4" aria-hidden="true" />
                            <span className="font-medium text-foreground tabular-nums">
                                {limitLabel(key, plan.limits[key] ?? null)}
                            </span>
                            {noun}
                        </span>
                    ))}
                </div>
            </div>

            <div className="mt-auto flex flex-wrap items-center justify-between gap-2 border-t border-border px-4 py-2.5">
                <div className="flex flex-col gap-0.5">
                    <span className="inline-flex items-center gap-1.5 text-xs text-muted-foreground">
                        <span
                            aria-hidden="true"
                            className={`size-1.5 rounded-full ${plan.isActive ? 'bg-success' : 'bg-muted-foreground/40'}`}
                        />
                        {plan.isActive ? 'Active' : 'Inactive'}
                    </span>
                    {plan.deleteBlockedReason !== null && (
                        <span className="text-xs text-muted-foreground">
                            {plan.deleteBlockedReason}
                        </span>
                    )}
                </div>
                <div className="flex items-center gap-1">
                    {plan.featured && (
                        <span className="inline-flex items-center gap-1 pr-1 text-xs text-muted-foreground">
                            <Star className="size-4" aria-hidden="true" />
                            Featured
                        </span>
                    )}
                    {canManage && (
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            aria-label={`Edit ${plan.name} plan`}
                            onClick={onEdit}
                        >
                            <Pencil aria-hidden="true" />
                            Edit
                        </Button>
                    )}
                    {canManage && (
                        <Button
                            type="button"
                            variant="destructive"
                            size="sm"
                            disabled={!plan.canDelete}
                            title={
                                plan.deleteBlockedReason ??
                                `Delete ${plan.name} plan`
                            }
                            aria-label={
                                plan.deleteBlockedReason ??
                                `Delete ${plan.name} plan`
                            }
                            onClick={onDelete}
                        >
                            <Trash2 aria-hidden="true" />
                            Delete
                        </Button>
                    )}
                </div>
            </div>
        </Card>
    );
}
