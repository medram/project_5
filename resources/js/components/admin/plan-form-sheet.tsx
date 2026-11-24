import { useForm } from '@inertiajs/react';
import { RefreshCw, Save, Trash2, type LucideIcon } from 'lucide-react';
import { type FormEvent, useEffect, useRef } from 'react';
import { formatBytes, metricIcons } from '@/components/billing/usage-metrics';
import { FormErrorSummary } from '@/components/forms/form-error-summary';
import { humanizeAdminValue } from '@/components/admin/format';
import { Button } from '@/components/ui/button';
import {
    Field,
    FieldDescription,
    FieldError,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { formatMoney } from '@/lib/money';
import { currentPlanPrice } from '@/lib/plan-pricing';
import plans from '@/routes/admin/plans';
import planPrices from '@/routes/admin/plans/prices';
import type { AdminPlanRecord } from '@/types/admin-billing';
import type { PlanLimits } from '@/types/billing';

type QuotaItem = {
    key: keyof PlanLimits;
    label: string;
    icon: LucideIcon;
};

const quotaItems: QuotaItem[] = [
    { key: 'links', label: 'Short links', icon: metricIcons.links },
    { key: 'link_pages', label: 'Link pages', icon: metricIcons.link_pages },
    { key: 'bio_pages', label: 'Bio pages', icon: metricIcons.bio_pages },
    {
        key: 'qr_campaigns',
        label: 'Direct QR campaigns',
        icon: metricIcons.qr_campaigns,
    },
    { key: 'folders', label: 'Folders', icon: metricIcons.folders },
    { key: 'domains', label: 'Custom domains', icon: metricIcons.domains },
    { key: 'pixels', label: 'Tracking pixels', icon: metricIcons.pixels },
    { key: 'members', label: 'Workspace members', icon: metricIcons.members },
    { key: 'webhooks', label: 'Webhooks', icon: metricIcons.webhooks },
    { key: 'api_keys', label: 'Active API keys', icon: metricIcons.api_keys },
    {
        key: 'storage_bytes',
        label: 'Retained media storage',
        icon: metricIcons.storage_bytes,
    },
];

const quotaGroups: Array<{ label: string; keys: Array<keyof PlanLimits> }> = [
    {
        label: 'Publishing',
        keys: ['links', 'link_pages', 'bio_pages', 'qr_campaigns'],
    },
    {
        label: 'Workspace',
        keys: [
            'folders',
            'domains',
            'pixels',
            'members',
            'webhooks',
            'api_keys',
        ],
    },
    { label: 'Storage', keys: ['storage_bytes'] },
];

const quotaByKey = Object.fromEntries(
    quotaItems.map((item) => [item.key, item]),
) as Record<keyof PlanLimits, QuotaItem>;

const fieldLabels: Record<string, string> = {
    code: 'Code',
    name: 'Name',
    description: 'Description',
    sort_order: 'Sort order',
    featured: 'Featured',
    is_active: 'Active',
    ...Object.fromEntries(
        quotaItems.map((item) => [`limits.${item.key}`, item.label]),
    ),
};

function fieldId(key: string): string {
    if (key.startsWith('limits.')) {
        return `plan-limit-${key.slice('limits.'.length)}`;
    }

    if (key === 'is_active') {
        return 'plan-active';
    }

    if (key === 'featured') {
        return 'plan-featured';
    }

    return `plan-${key.replaceAll('_', '-')}`;
}

function toFormLimits(limits: Record<string, number | null>) {
    return Object.fromEntries(
        Object.entries(limits).map(([key, value]) => [
            key,
            value === null ? '' : String(value),
        ]),
    );
}

export function PlanFormSheet({
    plan,
    initialLimits,
    currency,
    onClose,
}: {
    plan: AdminPlanRecord | null;
    initialLimits: Record<string, number | null>;
    currency: string;
    onClose: (message?: string) => void;
}) {
    const form = useForm({
        code: '',
        name: plan?.name ?? '',
        description: plan?.description ?? '',
        sort_order: plan?.sortOrder ?? 0,
        featured: plan?.featured ?? false,
        is_active: plan?.isActive ?? false,
        limits: toFormLimits(initialLimits),
    });
    const summaryRef = useRef<HTMLDivElement>(null);
    const errors = form.errors as Record<string, string | undefined>;
    const errorEntries = Object.entries(errors)
        .filter((entry): entry is [string, string] => Boolean(entry[1]))
        .map(([key, message]) => ({
            key,
            label: fieldLabels[key] ?? humanizeAdminValue(key),
            message,
            interactive: true,
        }));
    const errorCount = errorEntries.length;

    useEffect(() => {
        if (errorCount > 0) {
            summaryRef.current?.focus();
        }
    }, [errorCount]);

    function selectError(key: string) {
        const element = document.getElementById(fieldId(key));
        element?.focus();
        element?.scrollIntoView({ block: 'center', behavior: 'smooth' });
    }

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            name: data.name.trim(),
            description: data.description.trim() || null,
            limits: Object.fromEntries(
                Object.entries(data.limits).map(([key, value]) => [
                    key,
                    value === '' ? null : Number(value),
                ]),
            ),
        }));

        if (plan) {
            form.patch(plans.update(plan.code).url, {
                preserveScroll: true,
                onSuccess: () => onClose('Plan updated'),
            });
        } else {
            form.post(plans.store().url, {
                preserveScroll: true,
                onSuccess: () => onClose('Plan created'),
            });
        }
    }

    return (
        <Sheet open onOpenChange={(open) => !open && onClose()}>
            <SheetContent className="w-full gap-0 p-0 sm:max-w-xl">
                <SheetHeader className="border-b border-border pr-12">
                    <SheetTitle className="text-lg">
                        {plan ? plan.name : 'New plan'}
                    </SheetTitle>
                    <SheetDescription>
                        {plan
                            ? `${plan.code} · ${plan.currency.toUpperCase()} · ${plan.isActive ? 'Active' : 'Inactive'}`
                            : `New ${currency.toUpperCase()} tier. Quotas start from the Free plan.`}
                    </SheetDescription>
                </SheetHeader>

                <form
                    className="flex min-h-0 flex-1 flex-col"
                    onSubmit={submit}
                >
                    <div className="flex min-h-0 flex-1 flex-col gap-8 overflow-y-auto px-5 py-5">
                        <FormErrorSummary
                            summaryRef={summaryRef}
                            titleId="plan-form-errors"
                            entries={errorEntries}
                            onSelect={selectError}
                            className="mx-0 mb-0"
                        />

                        <section className="grid gap-4">
                            <h3 className="text-sm font-medium">Details</h3>

                            {!plan && (
                                <Field>
                                    <FieldLabel htmlFor="plan-code">
                                        Code
                                    </FieldLabel>
                                    <Input
                                        id="plan-code"
                                        value={form.data.code}
                                        required
                                        maxLength={40}
                                        autoComplete="off"
                                        autoCapitalize="none"
                                        spellCheck={false}
                                        className="h-9 font-mono"
                                        aria-invalid={Boolean(errors.code)}
                                        aria-describedby="plan-code-help"
                                        onChange={(event) =>
                                            form.setData(
                                                'code',
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <FieldDescription
                                        id="plan-code-help"
                                        className="text-xs"
                                    >
                                        Lowercase letters, numbers, underscores,
                                        and hyphens. The code cannot change
                                        later
                                    </FieldDescription>
                                    {errors.code && (
                                        <FieldError>{errors.code}</FieldError>
                                    )}
                                </Field>
                            )}

                            <Field>
                                <FieldLabel htmlFor="plan-name">
                                    Name
                                </FieldLabel>
                                <Input
                                    id="plan-name"
                                    value={form.data.name}
                                    required
                                    maxLength={80}
                                    className="h-9"
                                    aria-invalid={Boolean(errors.name)}
                                    onChange={(event) =>
                                        form.setData('name', event.target.value)
                                    }
                                />
                                {errors.name && (
                                    <FieldError>{errors.name}</FieldError>
                                )}
                            </Field>

                            <Field>
                                <FieldLabel htmlFor="plan-description">
                                    Description
                                </FieldLabel>
                                <Textarea
                                    id="plan-description"
                                    value={form.data.description}
                                    maxLength={500}
                                    rows={3}
                                    className="min-h-20"
                                    aria-invalid={Boolean(errors.description)}
                                    onChange={(event) =>
                                        form.setData(
                                            'description',
                                            event.target.value,
                                        )
                                    }
                                />
                                {errors.description && (
                                    <FieldError>
                                        {errors.description}
                                    </FieldError>
                                )}
                            </Field>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field>
                                    <FieldLabel htmlFor="plan-sort-order">
                                        Sort order
                                    </FieldLabel>
                                    <Input
                                        id="plan-sort-order"
                                        type="number"
                                        min={0}
                                        max={1000}
                                        value={form.data.sort_order}
                                        className="h-9 tabular-nums"
                                        aria-invalid={Boolean(
                                            errors.sort_order,
                                        )}
                                        onChange={(event) =>
                                            form.setData(
                                                'sort_order',
                                                event.target.value === ''
                                                    ? 0
                                                    : Number(
                                                          event.target.value,
                                                      ),
                                            )
                                        }
                                    />
                                    <FieldDescription className="text-xs">
                                        Lower values come first
                                    </FieldDescription>
                                    {errors.sort_order && (
                                        <FieldError>
                                            {errors.sort_order}
                                        </FieldError>
                                    )}
                                </Field>

                                <div className="grid content-end gap-3">
                                    <ToggleRow
                                        id="plan-featured"
                                        label="Featured"
                                        description="Highlight in the catalog"
                                        checked={form.data.featured}
                                        onCheckedChange={(checked) =>
                                            form.setData('featured', checked)
                                        }
                                    />
                                    <ToggleRow
                                        id="plan-active"
                                        label="Active"
                                        description={
                                            plan?.code === 'free'
                                                ? 'Always active'
                                                : 'Allow new checkouts'
                                        }
                                        checked={form.data.is_active}
                                        disabled={plan?.code === 'free'}
                                        onCheckedChange={(checked) =>
                                            form.setData('is_active', checked)
                                        }
                                    />
                                    {errors.is_active && (
                                        <FieldError>
                                            {errors.is_active}
                                        </FieldError>
                                    )}
                                </div>
                            </div>
                        </section>

                        <section className="grid gap-4">
                            <div>
                                <h3 className="text-sm font-medium">Quotas</h3>
                                <p className="mt-0.5 text-xs text-muted-foreground">
                                    Blank means unlimited, zero allows none
                                </p>
                            </div>

                            {quotaGroups.map((group) => (
                                <div key={group.label} className="grid gap-3">
                                    <p className="text-xs font-medium text-muted-foreground">
                                        {group.label}
                                    </p>
                                    <div
                                        className={
                                            group.keys.length === 1
                                                ? ''
                                                : 'grid gap-3 sm:grid-cols-2'
                                        }
                                    >
                                        {group.keys.map((key) => (
                                            <QuotaField
                                                key={key}
                                                item={quotaByKey[key]}
                                                value={
                                                    form.data.limits[key] ?? ''
                                                }
                                                error={errors[`limits.${key}`]}
                                                onChange={(value) =>
                                                    form.setData('limits', {
                                                        ...form.data.limits,
                                                        [key]: value,
                                                    })
                                                }
                                            />
                                        ))}
                                    </div>
                                </div>
                            ))}
                        </section>

                        {plan && plan.code !== 'free' && (
                            <section className="grid gap-4">
                                <div>
                                    <h3 className="text-sm font-medium">
                                        Stripe prices
                                    </h3>
                                    <p className="mt-0.5 text-xs text-muted-foreground">
                                        Prices save separately. Verify each
                                        price to enable checkout
                                    </p>
                                </div>

                                <div className="grid gap-3">
                                    <PriceEditor
                                        key={`month-${plan.stripeMonthlyPriceId ?? 'none'}-${plan.prices.length}`}
                                        plan={plan}
                                        interval="month"
                                    />
                                    <PriceEditor
                                        key={`year-${plan.stripeYearlyPriceId ?? 'none'}-${plan.prices.length}`}
                                        plan={plan}
                                        interval="year"
                                    />
                                </div>

                                {plan.prices.length > 0 && (
                                    <details className="rounded-xl border border-border px-3 py-2">
                                        <summary className="cursor-pointer text-sm text-muted-foreground select-none">
                                            Price history ({plan.prices.length})
                                        </summary>
                                        <ul className="mt-1 divide-y divide-border">
                                            {plan.prices.map((price) => (
                                                <li
                                                    key={`${price.id}-${price.interval}`}
                                                    className="flex flex-wrap items-center justify-between gap-x-3 gap-y-1 py-2"
                                                >
                                                    <div className="min-w-0">
                                                        <p className="font-mono text-[11px] leading-4 break-all text-muted-foreground">
                                                            {price.id}
                                                        </p>
                                                        <p className="text-xs text-muted-foreground">
                                                            {price.interval ===
                                                            'month'
                                                                ? 'Monthly'
                                                                : 'Yearly'}
                                                            {price.verified &&
                                                            price.amount !==
                                                                null
                                                                ? ` · ${formatMoney(price.amount, price.currency)}`
                                                                : ' · Unavailable'}
                                                        </p>
                                                    </div>
                                                    <span className="text-xs text-muted-foreground">
                                                        {price.verified
                                                            ? 'Verified'
                                                            : 'Unverified'}
                                                    </span>
                                                </li>
                                            ))}
                                        </ul>
                                    </details>
                                )}
                            </section>
                        )}

                        {plan?.code === 'free' && (
                            <p className="rounded-xl border border-dashed border-border px-3 py-2.5 text-xs text-muted-foreground">
                                Free plan · no Stripe price. Checkout never
                                charges for this tier
                            </p>
                        )}

                        {!plan && (
                            <p className="rounded-xl border border-dashed border-border px-3 py-2.5 text-xs text-muted-foreground">
                                Stripe prices can be connected after the plan is
                                created
                            </p>
                        )}
                    </div>

                    <SheetFooter className="flex-row items-center justify-end gap-2 border-t border-border">
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={() => onClose()}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            <Save aria-hidden="true" />
                            {form.processing
                                ? 'Saving…'
                                : plan
                                  ? 'Save plan'
                                  : 'Create plan'}
                        </Button>
                    </SheetFooter>
                </form>
            </SheetContent>
        </Sheet>
    );
}

function ToggleRow({
    id,
    label,
    description,
    checked,
    disabled = false,
    onCheckedChange,
}: {
    id: string;
    label: string;
    description: string;
    checked: boolean;
    disabled?: boolean;
    onCheckedChange: (checked: boolean) => void;
}) {
    return (
        <div className="flex items-center justify-between gap-4 rounded-xl border border-border px-3 py-2.5">
            <div className="min-w-0">
                <Label htmlFor={id} className="text-sm font-medium">
                    {label}
                </Label>
                <p className="mt-0.5 text-xs leading-4 text-muted-foreground">
                    {description}
                </p>
            </div>
            <Switch
                id={id}
                checked={checked}
                disabled={disabled}
                onCheckedChange={onCheckedChange}
            />
        </div>
    );
}

function QuotaField({
    item,
    value,
    error,
    onChange,
}: {
    item: QuotaItem;
    value: string;
    error?: string;
    onChange: (value: string) => void;
}) {
    const Icon = item.icon;
    const id = `plan-limit-${item.key}`;
    const max = item.key === 'storage_bytes' ? 9007199254740991 : 4294967295;
    const numericValue = Number(value);

    return (
        <Field>
            <FieldLabel htmlFor={id} className="flex items-center gap-1.5">
                <Icon
                    className="size-4 text-muted-foreground"
                    aria-hidden="true"
                />
                {item.label}
            </FieldLabel>
            <Input
                id={id}
                type="number"
                inputMode="numeric"
                min={0}
                max={max}
                step={1}
                placeholder="Unlimited"
                value={value}
                className="h-9 tabular-nums"
                aria-invalid={Boolean(error)}
                aria-describedby={error ? `${id}-error` : undefined}
                onChange={(event) => onChange(event.target.value)}
            />
            {item.key === 'storage_bytes' &&
                value !== '' &&
                Number.isFinite(numericValue) && (
                    <FieldDescription className="text-xs tabular-nums">
                        {formatBytes(numericValue)}
                    </FieldDescription>
                )}
            {error && <FieldError id={`${id}-error`}>{error}</FieldError>}
        </Field>
    );
}

function PriceEditor({
    plan,
    interval,
}: {
    plan: AdminPlanRecord;
    interval: 'month' | 'year';
}) {
    const current =
        interval === 'month'
            ? plan.stripeMonthlyPriceId
            : plan.stripeYearlyPriceId;
    const price = currentPlanPrice(plan, interval);
    const form = useForm({
        price_id: current ?? '',
        expected_current_price_id: current,
    });
    const label = interval === 'month' ? 'Monthly' : 'Yearly';
    const url = planPrices.sync({ plan: plan.code, interval }).url;
    const verified = price?.verified === true && price.amount !== null;
    const status = !current
        ? 'No price set'
        : verified
          ? formatMoney(price.amount ?? 0, price.currency)
          : 'Not verified';

    function verify() {
        form.post(url, { preserveScroll: true });
    }

    return (
        <div className="grid min-w-0 content-start gap-3 rounded-xl border border-border p-3">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <p className="text-sm font-medium">{label} price</p>
                <span className="inline-flex items-center gap-1.5 text-xs text-muted-foreground">
                    <span
                        aria-hidden="true"
                        className={`size-1.5 rounded-full ${verified ? 'bg-success' : current ? 'bg-warning' : 'bg-muted-foreground/40'}`}
                    />
                    {status}
                </span>
            </div>

            <Field>
                <FieldLabel
                    htmlFor={`${plan.code}-${interval}-price`}
                    className="text-xs text-muted-foreground"
                >
                    Stripe price ID
                </FieldLabel>
                <Input
                    id={`${plan.code}-${interval}-price`}
                    value={form.data.price_id}
                    maxLength={255}
                    placeholder="price_…"
                    autoComplete="off"
                    spellCheck={false}
                    className="h-9 font-mono text-xs"
                    aria-invalid={Boolean(form.errors.price_id)}
                    onChange={(event) =>
                        form.setData('price_id', event.target.value)
                    }
                    onKeyDown={(event) => {
                        if (event.key === 'Enter') {
                            event.preventDefault();
                            verify();
                        }
                    }}
                />
                {form.errors.price_id && (
                    <FieldError>{form.errors.price_id}</FieldError>
                )}
                {form.errors.expected_current_price_id && (
                    <FieldError>
                        {form.errors.expected_current_price_id}
                    </FieldError>
                )}
            </Field>

            {plan.checkout[interval] && (
                <p className="text-xs text-muted-foreground">
                    Checkout uses this price
                </p>
            )}

            <div className="flex flex-wrap gap-2">
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    disabled={form.processing || !form.data.price_id}
                    onClick={verify}
                >
                    <RefreshCw aria-hidden="true" />
                    {form.processing ? 'Verifying…' : 'Verify and sync'}
                </Button>
                {current && (
                    <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        disabled={form.processing}
                        onClick={() =>
                            form.delete(url, { preserveScroll: true })
                        }
                    >
                        <Trash2 aria-hidden="true" />
                        Clear price
                    </Button>
                )}
            </div>
        </div>
    );
}
