import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowUpRight, CreditCard, Plus } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { AdminConfirmDialog } from '@/components/admin/admin-confirm-dialog';
import { PlanCatalogCard } from '@/components/admin/plan-catalog-card';
import { PlanFormSheet } from '@/components/admin/plan-form-sheet';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import {
    Empty,
    EmptyContent,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import planRoutes from '@/routes/admin/plans';
import settings from '@/routes/admin/settings';
import type {
    AdminPlanRecord,
    AdminPlansPageProps,
} from '@/types/admin-billing';

const NEW_PLAN = 'new';

const emptyLimits: Record<string, number | null> = {
    links: null,
    folders: null,
    qr_campaigns: null,
    bio_pages: null,
    domains: null,
    pixels: null,
    members: null,
    webhooks: null,
    link_pages: null,
    api_keys: null,
    storage_bytes: null,
};

export default function AdminPlansIndex({
    plans,
    readiness,
    subscriptionCurrency,
}: AdminPlansPageProps) {
    const { auth } = usePage().props;
    const canManage = auth.adminAbilities.includes('admin.billing.manage');
    const canViewSettings = auth.adminAbilities.includes('admin.settings.view');
    const [editingCode, setEditingCode] = useState<string | null>(null);
    const [deleting, setDeleting] = useState<AdminPlanRecord | null>(null);
    const [processingDelete, setProcessingDelete] = useState(false);
    const [deleteError, setDeleteError] = useState<string | null>(null);

    const freePlan = plans.find((plan) => plan.code === 'free');
    const editingPlan =
        editingCode !== null && editingCode !== NEW_PLAN
            ? (plans.find((plan) => plan.code === editingCode) ?? null)
            : null;
    const activeCount = plans.filter((plan) => plan.isActive).length;
    const checkoutReadyCount = plans.filter(
        (plan) =>
            ((readiness.stripeReady && plan.checkout.month) ||
                (readiness.paypalReady && plan.paypalCheckout.month)) &&
            ((readiness.stripeReady && plan.checkout.year) ||
                (readiness.paypalReady && plan.paypalCheckout.year)),
    ).length;
    const checkoutReady = readiness.checkoutReady === true;
    const missing = readiness.missing ?? [];

    function confirmDelete() {
        if (!deleting) {
            return;
        }

        setProcessingDelete(true);
        router.delete(planRoutes.destroy(deleting.code).url, {
            preserveScroll: true,
            onSuccess: () => setDeleting(null),
            onError: (errors) => {
                setDeleting(null);
                setDeleteError(
                    Object.values(errors)[0] ??
                        'The plan could not be deleted.',
                );
            },
            onFinish: () => setProcessingDelete(false),
        });
    }

    return (
        <>
            <Head title="Plans — Administration" />
            <div className="mx-auto flex w-full max-w-7xl min-w-0 flex-1 flex-col gap-4 px-5 pt-4 pb-10">
                <PageHeader
                    title="Plans"
                    description="Workspace tiers, quotas, and payment provider prices."
                    actions={
                        canManage ? (
                            <Button
                                type="button"
                                onClick={() => setEditingCode(NEW_PLAN)}
                            >
                                <Plus aria-hidden="true" />
                                New plan
                            </Button>
                        ) : undefined
                    }
                />

                <section
                    aria-label="Plan summary"
                    className="rounded-2xl border border-border bg-card px-4 py-3 shadow-[var(--shadow-surface)]"
                >
                    <div className="flex flex-wrap items-center gap-x-4 gap-y-2">
                        <span className="inline-flex items-center gap-2 text-sm font-medium">
                            <span
                                aria-hidden="true"
                                className={`size-2 rounded-full ${checkoutReady ? 'bg-success' : 'bg-warning'}`}
                            />
                            {checkoutReady
                                ? 'Checkout ready'
                                : 'Checkout not configured'}
                        </span>
                        <span className="text-xs text-muted-foreground">
                            {checkoutReady
                                ? 'Prices can be verified and synced'
                                : 'Prices save; checkout stays off'}
                        </span>
                        <div className="ml-auto flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-muted-foreground">
                            <span>
                                <strong className="font-medium text-foreground tabular-nums">
                                    {plans.length}
                                </strong>{' '}
                                plans
                            </span>
                            <span aria-hidden="true" className="opacity-40">
                                ·
                            </span>
                            <span>
                                <strong className="font-medium text-foreground tabular-nums">
                                    {activeCount}
                                </strong>{' '}
                                active
                            </span>
                            <span aria-hidden="true" className="opacity-40">
                                ·
                            </span>
                            <span>
                                <strong className="font-medium text-foreground tabular-nums">
                                    {checkoutReadyCount}
                                </strong>{' '}
                                ready
                            </span>
                            {canViewSettings && (
                                <>
                                    <span
                                        aria-hidden="true"
                                        className="opacity-40"
                                    >
                                        ·
                                    </span>
                                    <Link
                                        href={
                                            settings.show('payment-gateways').url
                                        }
                                        className="inline-flex items-center gap-0.5 rounded-sm font-medium text-foreground underline-offset-4 hover:underline focus-visible:ring-2 focus-visible:ring-ring/40 focus-visible:outline-none"
                                    >
                                        Billing settings
                                        <ArrowUpRight
                                            className="size-3.5"
                                            aria-hidden="true"
                                        />
                                    </Link>
                                </>
                            )}
                        </div>
                    </div>
                    {!checkoutReady && missing.length > 0 && (
                        <details className="mt-2">
                            <summary className="cursor-pointer text-xs text-muted-foreground select-none hover:text-foreground">
                                Missing Stripe setup
                            </summary>
                            <div className="mt-2 flex flex-wrap gap-1.5">
                                {missing.map((key) => (
                                    <code
                                        key={key}
                                        className="rounded-md border border-border bg-surface-secondary px-1.5 py-0.5 font-mono text-[11px] leading-4 text-muted-foreground"
                                    >
                                        {key}
                                    </code>
                                ))}
                            </div>
                        </details>
                    )}
                </section>

                {deleteError && (
                    <p role="alert" className="text-sm text-destructive">
                        {deleteError}
                    </p>
                )}

                {plans.length === 0 ? (
                    <Empty>
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <CreditCard aria-hidden="true" />
                            </EmptyMedia>
                            <EmptyTitle>No plans configured</EmptyTitle>
                            <EmptyDescription>
                                Create the first tier to assign workspace
                                entitlements and connect payment provider prices
                            </EmptyDescription>
                        </EmptyHeader>
                        {canManage && (
                            <EmptyContent>
                                <Button
                                    type="button"
                                    onClick={() => setEditingCode(NEW_PLAN)}
                                >
                                    <Plus aria-hidden="true" />
                                    New plan
                                </Button>
                            </EmptyContent>
                        )}
                    </Empty>
                ) : (
                    <section
                        aria-label="Plan catalog"
                        className="grid gap-4 md:grid-cols-2 xl:grid-cols-3"
                    >
                        {plans.map((plan) => (
                            <PlanCatalogCard
                                key={plan.code}
                                plan={plan}
                                canManage={canManage}
                                onEdit={() => setEditingCode(plan.code)}
                                onDelete={() => {
                                    setDeleteError(null);
                                    setDeleting(plan);
                                }}
                            />
                        ))}
                    </section>
                )}
            </div>

            {editingCode !== null && (
                <PlanFormSheet
                    key={editingCode}
                    plan={editingPlan}
                    initialLimits={
                        editingPlan?.limits ?? freePlan?.limits ?? emptyLimits
                    }
                    currency={editingPlan?.currency ?? subscriptionCurrency}
                    onClose={(message) => {
                        setEditingCode(null);

                        if (message) {
                            toast.success(message);
                        }
                    }}
                />
            )}

            <AdminConfirmDialog
                confirmation={
                    deleting
                        ? {
                              title: `Delete ${deleting.name}?`,
                              description:
                                  'This removes the plan from the catalog. It cannot be undone.',
                              confirmLabel: 'Delete plan',
                              destructive: true,
                          }
                        : null
                }
                processing={processingDelete}
                onCancel={() => setDeleting(null)}
                onConfirm={confirmDelete}
            />
        </>
    );
}
