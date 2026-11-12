import { Head, useForm } from '@inertiajs/react';
import { CreditCard, LockKeyhole, ShieldAlert } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { update as updatePlan } from '@/routes/admin/plans';
import type {
    AdminPlanRecord,
    AdminPlansPageProps,
} from '@/types/admin-billing';

function money(cents: number, currency: string): string {
    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: currency.toUpperCase(),
    }).format(cents / 100);
}

function limitLabel(value: number | null): string {
    return value === null ? 'Unlimited' : value.toLocaleString();
}

export default function AdminPlansIndex({
    plans: catalog,
    readiness,
}: AdminPlansPageProps) {
    return (
        <>
            <Head title="Plans — Administration" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <header className="space-y-1">
                    <div className="flex items-center gap-2 text-sm text-muted-foreground">
                        <CreditCard className="size-4" aria-hidden="true" />
                        <span>Platform administration</span>
                    </div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Plans
                    </h1>
                    <p className="max-w-3xl text-sm text-muted-foreground">
                        Exact catalog prices and quotas are locked to the
                        product contract in this release. Administrators may
                        edit display metadata, active state, and Stripe price
                        mappings only.
                    </p>
                </header>

                <div className="flex flex-wrap items-center gap-2 rounded-lg border p-4 text-sm">
                    {readiness.checkoutReady ? (
                        <Badge variant="outline">Stripe checkout ready</Badge>
                    ) : (
                        <>
                            <ShieldAlert
                                className="size-4 text-amber-600"
                                aria-hidden="true"
                            />
                            <span>
                                Provider checkout is disabled until configured:
                            </span>
                            <code className="text-xs">
                                {readiness.missing.join(', ')}
                            </code>
                        </>
                    )}
                </div>

                <div className="grid gap-4 xl:grid-cols-2">
                    {catalog.map((plan) => (
                        <PlanEditor key={plan.code} plan={plan} />
                    ))}
                </div>
            </div>
        </>
    );
}

function PlanEditor({ plan }: { plan: AdminPlanRecord }) {
    const form = useForm({
        name: plan.name,
        description: plan.description ?? '',
        sort_order: plan.sortOrder,
        featured: plan.featured,
        is_active: plan.isActive,
        stripe_monthly_price_id: plan.stripeMonthlyPriceId ?? '',
        stripe_yearly_price_id: plan.stripeYearlyPriceId ?? '',
    });

    return (
        <Card className="min-w-0">
            <CardHeader className="gap-2">
                <div className="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <CardTitle>{form.data.name}</CardTitle>
                        <CardDescription>
                            <code className="text-xs">{plan.code}</code> ·{' '}
                            {plan.currency.toUpperCase()}
                        </CardDescription>
                    </div>
                    <div className="flex flex-wrap gap-1">
                        <Badge
                            variant={plan.isActive ? 'secondary' : 'outline'}
                        >
                            {plan.isActive ? 'Active' : 'Inactive'}
                        </Badge>
                        {plan.featured && (
                            <Badge variant="outline">Featured</Badge>
                        )}
                    </div>
                </div>
            </CardHeader>
            <CardContent className="grid gap-5">
                <div className="grid gap-2 rounded-md border bg-muted/30 p-3 text-sm sm:grid-cols-2">
                    <div>
                        <p className="text-xs text-muted-foreground">
                            Monthly price
                        </p>
                        <p className="font-medium">
                            {money(plan.monthlyPrice, plan.currency)}
                        </p>
                    </div>
                    <div>
                        <p className="text-xs text-muted-foreground">
                            Annual price
                        </p>
                        <p className="font-medium">
                            {money(plan.annualPrice, plan.currency)}
                        </p>
                    </div>
                    <div className="flex items-center gap-1 text-xs text-muted-foreground sm:col-span-2">
                        <LockKeyhole className="size-3" aria-hidden="true" />
                        Code, currency, amounts, and quotas are locked to the
                        product contract.
                    </div>
                </div>

                <form
                    className="grid gap-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.patch(updatePlan(plan.code).url, {
                            preserveScroll: true,
                        });
                    }}
                >
                    <div className="grid gap-2">
                        <Label htmlFor={`plan-name-${plan.code}`}>Name</Label>
                        <Input
                            id={`plan-name-${plan.code}`}
                            value={form.data.name}
                            maxLength={80}
                            required
                            onChange={(event) =>
                                form.setData('name', event.target.value)
                            }
                        />
                        {form.errors.name && (
                            <p className="text-sm text-destructive">
                                {form.errors.name}
                            </p>
                        )}
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor={`plan-description-${plan.code}`}>
                            Description
                        </Label>
                        <Textarea
                            id={`plan-description-${plan.code}`}
                            value={form.data.description}
                            maxLength={500}
                            rows={3}
                            onChange={(event) =>
                                form.setData('description', event.target.value)
                            }
                        />
                        {form.errors.description && (
                            <p className="text-sm text-destructive">
                                {form.errors.description}
                            </p>
                        )}
                    </div>
                    <div className="grid gap-3 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor={`plan-order-${plan.code}`}>
                                Sort order
                            </Label>
                            <Input
                                id={`plan-order-${plan.code}`}
                                type="number"
                                min={0}
                                max={1000}
                                value={form.data.sort_order}
                                onChange={(event) =>
                                    form.setData(
                                        'sort_order',
                                        Number(event.target.value),
                                    )
                                }
                            />
                        </div>
                        <div className="flex flex-wrap items-end gap-4 pb-2">
                            <label className="flex items-center gap-2 text-sm">
                                <Checkbox
                                    checked={form.data.featured}
                                    onCheckedChange={(checked) =>
                                        form.setData(
                                            'featured',
                                            checked === true,
                                        )
                                    }
                                />
                                Featured
                            </label>
                            <label className="flex items-center gap-2 text-sm">
                                <Checkbox
                                    checked={form.data.is_active}
                                    onCheckedChange={(checked) =>
                                        form.setData(
                                            'is_active',
                                            checked === true,
                                        )
                                    }
                                />
                                Active
                            </label>
                        </div>
                    </div>

                    <div className="grid gap-3 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor={`plan-monthly-${plan.code}`}>
                                Stripe monthly price ID
                            </Label>
                            <Input
                                id={`plan-monthly-${plan.code}`}
                                value={form.data.stripe_monthly_price_id}
                                maxLength={255}
                                placeholder="price_…"
                                onChange={(event) =>
                                    form.setData(
                                        'stripe_monthly_price_id',
                                        event.target.value,
                                    )
                                }
                            />
                            <p className="text-xs text-muted-foreground">
                                {plan.checkout.month
                                    ? 'Monthly checkout mapped'
                                    : 'Monthly checkout unavailable'}
                            </p>
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor={`plan-yearly-${plan.code}`}>
                                Stripe yearly price ID
                            </Label>
                            <Input
                                id={`plan-yearly-${plan.code}`}
                                value={form.data.stripe_yearly_price_id}
                                maxLength={255}
                                placeholder="price_…"
                                onChange={(event) =>
                                    form.setData(
                                        'stripe_yearly_price_id',
                                        event.target.value,
                                    )
                                }
                            />
                            <p className="text-xs text-muted-foreground">
                                {plan.checkout.year
                                    ? 'Yearly checkout mapped'
                                    : 'Yearly checkout unavailable'}
                            </p>
                        </div>
                    </div>

                    <div>
                        <p className="text-sm font-medium">Quotas</p>
                        <div className="mt-2 grid grid-cols-2 gap-2 text-xs text-muted-foreground sm:grid-cols-3">
                            {Object.entries(plan.limits).map(([key, value]) => (
                                <div key={key}>
                                    <span className="capitalize">
                                        {key.replaceAll('_', ' ')}
                                    </span>
                                    <span className="block font-medium text-foreground">
                                        {value === null
                                            ? 'Unlimited'
                                            : key === 'storage_bytes'
                                              ? `${Math.round(value / 1024 / 1024).toLocaleString()} MB`
                                              : limitLabel(value)}
                                    </span>
                                </div>
                            ))}
                        </div>
                    </div>

                    <Button
                        type="submit"
                        className="min-h-11 w-full sm:w-fit"
                        disabled={form.processing}
                    >
                        {form.processing ? 'Saving…' : 'Save plan'}
                    </Button>
                </form>
            </CardContent>
        </Card>
    );
}
