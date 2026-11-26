import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { type FormEvent, useRef, useState } from 'react';
import {
    Ban,
    Plus,
    RotateCcw,
    Search,
    Trash2,
    TriangleAlert,
} from 'lucide-react';
import {
    AdminConfirmDialog,
    type AdminConfirmation,
} from '@/components/admin/admin-confirm-dialog';
import { formatAdminDate, humanizeAdminValue } from '@/components/admin/format';
import { PageHeader } from '@/components/page-header';
import { UserAvatar } from '@/components/user-avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import settings from '@/routes/admin/settings';
import subscriptions from '@/routes/admin/subscriptions';
import type {
    AdminOverride,
    AdminSubscriptionRow,
    AdminSubscriptionsPageProps,
} from '@/types/admin-billing';

type ProviderBadge = {
    label: string;
    variant: 'secondary' | 'success' | 'warning' | 'destructive' | 'outline';
};

function formatAdminShortDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Intl.DateTimeFormat(undefined, {
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    }).format(new Date(value));
}

function providerBadge(status: string | null): ProviderBadge {
    if (!status) {
        return { label: 'No subscription', variant: 'secondary' };
    }

    const normalized = status.toLowerCase();

    if (normalized === 'active' || normalized === 'trialing') {
        return { label: humanizeAdminValue(status), variant: 'success' };
    }

    if (
        normalized === 'past_due' ||
        normalized === 'unpaid' ||
        normalized === 'payment_failed' ||
        normalized === 'suspended' ||
        normalized === 'incomplete'
    ) {
        return { label: humanizeAdminValue(status), variant: 'warning' };
    }

    if (normalized === 'canceled' || normalized === 'cancelled' || normalized === 'incomplete_expired') {
        return { label: humanizeAdminValue(status), variant: 'destructive' };
    }

    return { label: humanizeAdminValue(status), variant: 'outline' };
}

export default function AdminSubscriptionsIndex({
    workspaces,
    filters,
    plans,
    sources,
    readiness,
}: AdminSubscriptionsPageProps) {
    const { auth } = usePage().props;
    const canManage = auth.adminAbilities.includes('admin.billing.manage');
    const [query, setQuery] = useState(filters.q);
    const [overrideTarget, setOverrideTarget] =
        useState<AdminSubscriptionRow | null>(null);
    const [revokeTarget, setRevokeTarget] = useState<{
        workspace: AdminSubscriptionRow;
        override: AdminOverride;
    } | null>(null);
    const [pending, setPending] = useState<{
        confirmation: AdminConfirmation;
        run: () => void;
    } | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [processing, setProcessing] = useState(false);
    const overrideTrigger = useRef<HTMLButtonElement | null>(null);
    const revokeTrigger = useRef<HTMLButtonElement | null>(null);

    function focusResults() {
        document.getElementById('subscriptions-results')?.focus();
    }

    function search(event: FormEvent) {
        event.preventDefault();
        router.get(
            subscriptions.index().url,
            { q: query.trim() || undefined },
            { preserveScroll: true, onSuccess: focusResults },
        );
    }

    function clearSearch() {
        setQuery('');
        router.get(
            subscriptions.index().url,
            {},
            { preserveScroll: true, onSuccess: focusResults },
        );
    }

    function openOverride(
        workspace: AdminSubscriptionRow,
        trigger: HTMLButtonElement,
    ) {
        overrideTrigger.current = trigger;
        setOverrideTarget(workspace);
    }

    function closeOverrideDialog() {
        setOverrideTarget(null);
        requestAnimationFrame(() => overrideTrigger.current?.focus());
    }

    function openRevoke(
        workspace: AdminSubscriptionRow,
        override: AdminOverride,
        trigger: HTMLButtonElement,
    ) {
        revokeTrigger.current = trigger;
        setRevokeTarget({ workspace, override });
    }

    function closeRevokeDialog() {
        setRevokeTarget(null);
        requestAnimationFrame(() => revokeTrigger.current?.focus());
    }

    function providerAction(
        workspace: AdminSubscriptionRow,
        action: 'cancel' | 'resume',
    ) {
        setError(null);
        setPending({
            confirmation: {
                title:
                    action === 'cancel'
                        ? `Cancel ${workspace.workspace.name}?`
                        : `Resume ${workspace.workspace.name}?`,
                description:
                    action === 'cancel'
                        ? workspace.provider.name === 'paypal'
                            ? 'PayPal cancels immediately and access ends immediately unless another entitlement applies.'
                            : 'Stripe cancels at the end of the current billing period. Local status and renewal dates are not edited directly.'
                        : 'Stripe resumes the subscription within its current grace period.',
                confirmLabel:
                    action === 'cancel'
                        ? workspace.provider.name === 'paypal' ? 'Cancel immediately' : 'Cancel at period end'
                        : 'Resume subscription',
                destructive: action === 'cancel',
            },
            run: () => {
                setProcessing(true);
                router.post(
                    action === 'cancel'
                        ? subscriptions.cancel(workspace.workspace.id).url
                        : subscriptions.resume(workspace.workspace.id).url,
                    { provider: workspace.provider.name },
                    {
                        preserveScroll: true,
                        onSuccess: () => setPending(null),
                        onError: (errors) => {
                            setPending(null);
                            setError(
                                Object.values(errors)[0] ??
                                    'The provider action failed.',
                            );
                        },
                        onFinish: () => setProcessing(false),
                    },
                );
            },
        });
    }

    return (
        <>
            <Head title="Subscriptions — Administration" />
            <div className="mx-auto flex w-full max-w-7xl min-w-0 flex-1 flex-col gap-4 px-5 pt-4 pb-10">
                <PageHeader
                    title="Subscriptions and overrides"
                    description="Effective entitlements, local overrides, and provider-owned billing state. Payment data and provider secrets are never exposed."
                />

                {!readiness.configured && (
                    <div
                        role="status"
                        className="flex flex-col gap-3 rounded-xl border border-warning/30 bg-warning-soft px-4 py-3 text-sm text-warning-soft-foreground sm:flex-row sm:items-start sm:justify-between"
                    >
                        <div className="flex items-start gap-2">
                            <TriangleAlert
                                aria-hidden="true"
                                className="mt-0.5 size-4 shrink-0"
                            />
                            <div>
                                <p className="font-medium">
                                    Stripe billing is not configured.
                                </p>
                                <p className="text-xs">
                                    Stripe cancellation and resume are unavailable
                                    until Stripe keys are added.
                                </p>
                                {readiness.missing.length > 0 && (
                                    <details className="mt-1">
                                        <summary className="cursor-pointer text-xs">
                                            Missing configuration
                                        </summary>
                                        <p className="mt-1 font-mono text-xs">
                                            {readiness.missing.join(', ')}
                                        </p>
                                    </details>
                                )}
                            </div>
                        </div>
                        <Button
                            asChild
                            size="sm"
                            variant="outline"
                            className="shrink-0 max-lg:min-h-11"
                        >
                            <Link href={settings.show('payment-gateways').url}>
                                Open billing settings
                            </Link>
                        </Button>
                    </div>
                )}

                <form
                    onSubmit={search}
                    role="search"
                    className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-end"
                >
                    <Input
                        id="subscription-search"
                        value={query}
                        maxLength={100}
                        placeholder="Search workspace, owner, or email"
                        aria-label="Search subscriptions"
                        className="h-11 w-full sm:max-w-sm lg:h-9"
                        onChange={(event) => setQuery(event.target.value)}
                    />
                    <Button
                        type="submit"
                        size="sm"
                        className="h-11 w-full focus-visible:ring-ring/50 sm:w-auto lg:h-9"
                    >
                        <Search data-icon="inline-start" />
                        Search
                    </Button>
                </form>

                {error && (
                    <p
                        role="alert"
                        className="rounded-md border border-destructive/40 bg-destructive/10 px-4 py-3 text-sm text-destructive"
                    >
                        {error}
                    </p>
                )}

                <div
                    id="subscriptions-results"
                    tabIndex={-1}
                    className="outline-none"
                >
                    {workspaces.data.length === 0 ? (
                        <Card variant="transparent" role="status">
                            <CardContent className="flex flex-col items-center gap-3 p-8 text-center">
                                <p className="text-sm text-muted-foreground">
                                    No workspaces match this search.
                                </p>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    className="max-lg:min-h-11"
                                    onClick={clearSearch}
                                >
                                    Clear search
                                </Button>
                            </CardContent>
                        </Card>
                    ) : (
                        <>
                            <div className="lp-table-shell hidden lg:block">
                                <div className="overflow-x-auto">
                                    <table
                                        className="w-full min-w-[980px] table-fixed border-collapse text-sm"
                                        aria-label="Subscriptions"
                                    >
                                        <colgroup>
                                            <col className="w-[32%]" />
                                            <col className="w-[16%]" />
                                            <col className="w-[16%]" />
                                            <col className="w-[28%]" />
                                            <col className="w-[8%]" />
                                        </colgroup>
                                        <thead className="bg-surface-secondary text-left text-xs text-muted-foreground">
                                            <tr>
                                                <th
                                                    scope="col"
                                                    className="px-4 py-2.5 font-medium"
                                                >
                                                    Workspace
                                                </th>
                                                <th
                                                    scope="col"
                                                    className="px-4 py-2.5 font-medium"
                                                >
                                                    Plan
                                                </th>
                                                <th
                                                    scope="col"
                                                    className="px-4 py-2.5 font-medium"
                                                >
                                                    Provider
                                                </th>
                                                <th
                                                    scope="col"
                                                    className="px-4 py-2.5 font-medium"
                                                >
                                                    Overrides
                                                </th>
                                                <th
                                                    scope="col"
                                                    className="px-4 py-2.5 text-right font-medium"
                                                >
                                                    <span className="sr-only">
                                                        Actions
                                                    </span>
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {workspaces.data.map(
                                                (workspace) => {
                                                    const provider =
                                                        providerBadge(
                                                            workspace.provider
                                                                .status,
                                                        );

                                                    return (
                                                        <tr
                                                            key={
                                                                workspace
                                                                    .workspace
                                                                    .id
                                                            }
                                                            className="border-t border-separator align-top"
                                                        >
                                                            <th
                                                                scope="row"
                                                                className="px-4 py-3 text-left font-normal"
                                                            >
                                                                <div className="flex items-center gap-3">
                                                                    <UserAvatar
                                                                        name={
                                                                            workspace
                                                                                .workspace
                                                                                .owner
                                                                                ?.name ??
                                                                            workspace
                                                                                .workspace
                                                                                .name
                                                                        }
                                                                        seed={
                                                                            workspace
                                                                                .workspace
                                                                                .owner
                                                                                ?.id ??
                                                                            workspace
                                                                                .workspace
                                                                                .id
                                                                        }
                                                                    />
                                                                    <div className="min-w-0">
                                                                        <p
                                                                            className="truncate text-sm font-medium"
                                                                            title={
                                                                                workspace
                                                                                    .workspace
                                                                                    .name
                                                                            }
                                                                        >
                                                                            {
                                                                                workspace
                                                                                    .workspace
                                                                                    .name
                                                                            }
                                                                        </p>
                                                                        <p
                                                                            className="truncate text-xs text-muted-foreground"
                                                                            title={`${workspace.workspace.owner?.name ?? 'No owner'} · ${workspace.workspace.owner?.email ?? '—'} · ${workspace.workspace.membersCount} ${workspace.workspace.membersCount === 1 ? 'member' : 'members'}`}
                                                                        >
                                                                            {workspace
                                                                                .workspace
                                                                                .owner
                                                                                ?.name ??
                                                                                'No owner'}{' '}
                                                                            ·{' '}
                                                                            {workspace
                                                                                .workspace
                                                                                .owner
                                                                                ?.email ??
                                                                                '—'}{' '}
                                                                            ·{' '}
                                                                            {
                                                                                workspace
                                                                                    .workspace
                                                                                    .membersCount
                                                                            }{' '}
                                                                            {workspace
                                                                                .workspace
                                                                                .membersCount ===
                                                                            1
                                                                                ? 'member'
                                                                                : 'members'}
                                                                        </p>
                                                                    </div>
                                                                </div>
                                                            </th>
                                                            <td className="px-4 py-3">
                                                                <div className="flex flex-col items-start gap-1">
                                                                    <Badge
                                                                        variant="secondary"
                                                                        className="text-foreground"
                                                                    >
                                                                        {
                                                                            workspace
                                                                                .effectivePlan
                                                                                .name
                                                                        }
                                                                    </Badge>
                                                                    <span className="text-xs text-muted-foreground">
                                                                        Via{' '}
                                                                        {humanizeAdminValue(
                                                                            workspace
                                                                                .effectivePlan
                                                                                .source,
                                                                        )}
                                                                    </span>
                                                                    {workspace
                                                                        .effectivePlan
                                                                        .overrideExpiresAt && (
                                                                        <span
                                                                            className="text-xs text-muted-foreground"
                                                                            title={formatAdminDate(
                                                                                workspace
                                                                                    .effectivePlan
                                                                                    .overrideExpiresAt,
                                                                            )}
                                                                        >
                                                                            Expires{' '}
                                                                            <span className="font-medium text-foreground tabular-nums">
                                                                                {formatAdminShortDate(
                                                                                    workspace
                                                                                        .effectivePlan
                                                                                        .overrideExpiresAt,
                                                                                )}
                                                                            </span>
                                                                        </span>
                                                                    )}
                                                                </div>
                                                            </td>
                                                            <td className="px-4 py-3">
                                                                <div className="flex flex-col items-start gap-1">
                                                                    {workspace.provider.name && (
                                                                        <span className="text-xs font-medium">{workspace.provider.name === 'paypal' ? 'PayPal' : 'Stripe'}</span>
                                                                    )}
                                                                    {workspace.provider.amount !== null && workspace.provider.currency && (
                                                                        <span className="text-xs text-muted-foreground">
                                                                            {new Intl.NumberFormat(undefined, { style: 'currency', currency: workspace.provider.currency }).format(workspace.provider.amount / 100)} / {workspace.provider.interval}
                                                                        </span>
                                                                    )}
                                                                    <Badge
                                                                        variant={
                                                                            provider.variant
                                                                        }
                                                                    >
                                                                        {
                                                                            provider.label
                                                                        }
                                                                    </Badge>
                                                                    {workspace
                                                                        .provider
                                                                        .currentPeriodEnd && (
                                                                        <span
                                                                            className="text-xs text-muted-foreground"
                                                                            title={formatAdminDate(
                                                                                workspace
                                                                                    .provider
                                                                                    .currentPeriodEnd,
                                                                            )}
                                                                        >
                                                                            Renews{' '}
                                                                            <span className="font-medium text-foreground tabular-nums">
                                                                                {formatAdminShortDate(
                                                                                    workspace
                                                                                        .provider
                                                                                        .currentPeriodEnd,
                                                                                )}
                                                                            </span>
                                                                        </span>
                                                                    )}
                                                                    {workspace
                                                                        .provider
                                                                        .endsAt && (
                                                                        <span
                                                                            className="text-xs text-muted-foreground"
                                                                            title={formatAdminDate(
                                                                                workspace
                                                                                    .provider
                                                                                    .endsAt,
                                                                            )}
                                                                        >
                                                                            Cancels{' '}
                                                                            <span className="font-medium text-foreground tabular-nums">
                                                                                {formatAdminShortDate(
                                                                                    workspace
                                                                                        .provider
                                                                                        .endsAt,
                                                                                )}
                                                                            </span>
                                                                        </span>
                                                                    )}
                                                                    {workspace
                                                                        .provider
                                                                        .priceId && (
                                                                        <span className="font-mono text-[11px] break-all text-muted-foreground">
                                                                            {
                                                                                workspace
                                                                                    .provider
                                                                                    .priceId
                                                                            }
                                                                        </span>
                                                                    )}
                                                                </div>
                                                            </td>
                                                            <td className="px-4 py-3">
                                                                {workspace
                                                                    .overrides
                                                                    .length ===
                                                                0 ? (
                                                                    <span className="text-xs text-muted-foreground">
                                                                        None
                                                                    </span>
                                                                ) : (
                                                                    <OverrideList
                                                                        workspace={
                                                                            workspace
                                                                        }
                                                                        canManage={
                                                                            canManage
                                                                        }
                                                                        onRevoke={
                                                                            openRevoke
                                                                        }
                                                                        variant="table"
                                                                    />
                                                                )}
                                                            </td>
                                                            <td className="px-4 py-3">
                                                                <div className="flex justify-end gap-1">
                                                                    {canManage && (
                                                                        <Button
                                                                            type="button"
                                                                            size="icon"
                                                                            variant="secondary"
                                                                            className="size-8 rounded-full"
                                                                            title="Add override"
                                                                            aria-label={`Add override for ${workspace.workspace.name}`}
                                                                            onClick={(
                                                                                event,
                                                                            ) =>
                                                                                openOverride(
                                                                                    workspace,
                                                                                    event.currentTarget,
                                                                                )
                                                                            }
                                                                        >
                                                                            <Plus aria-hidden="true" />
                                                                        </Button>
                                                                    )}
                                                                    {canManage &&
                                                                        workspace
                                                                            .provider
                                                                            .canCancel && (
                                                                            <Button
                                                                                type="button"
                                                                                size="icon"
                                                                                variant="destructive"
                                                                                className="size-8 focus-visible:ring-destructive/40"
                                                                                title={workspace.provider.name === 'paypal' ? 'Cancel immediately' : 'Cancel at period end'}
                                                                                aria-label={`Cancel ${workspace.workspace.name}${workspace.provider.name === 'paypal' ? ' immediately' : ' at period end'}`}
                                                                                onClick={() =>
                                                                                    providerAction(
                                                                                        workspace,
                                                                                        'cancel',
                                                                                    )
                                                                                }
                                                                            >
                                                                                <Ban aria-hidden="true" />
                                                                            </Button>
                                                                        )}
                                                                    {canManage &&
                                                                        workspace
                                                                            .provider
                                                                            .canResume && (
                                                                            <Button
                                                                                type="button"
                                                                                size="icon"
                                                                                variant="secondary"
                                                                                className="size-8 rounded-full"
                                                                                title="Resume"
                                                                                aria-label={`Resume ${workspace.workspace.name}`}
                                                                                onClick={() =>
                                                                                    providerAction(
                                                                                        workspace,
                                                                                        'resume',
                                                                                    )
                                                                                }
                                                                            >
                                                                                <RotateCcw aria-hidden="true" />
                                                                            </Button>
                                                                        )}
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    );
                                                },
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div className="grid gap-3 lg:hidden" role="list">
                                {workspaces.data.map((workspace) => {
                                    const provider = providerBadge(
                                        workspace.provider.status,
                                    );

                                    return (
                                        <Card
                                            key={workspace.workspace.id}
                                            className="gap-2 py-3"
                                            role="listitem"
                                            aria-labelledby={`workspace-${workspace.workspace.id}-title`}
                                        >
                                            <CardHeader className="gap-1">
                                                <div className="flex flex-wrap items-start justify-between gap-x-4 gap-y-2">
                                                    <div className="flex min-w-0 items-start gap-3">
                                                        <UserAvatar
                                                            name={
                                                                workspace
                                                                    .workspace
                                                                    .owner
                                                                    ?.name ??
                                                                workspace
                                                                    .workspace
                                                                    .name
                                                            }
                                                            seed={
                                                                workspace
                                                                    .workspace
                                                                    .owner
                                                                    ?.id ??
                                                                workspace
                                                                    .workspace
                                                                    .id
                                                            }
                                                        />
                                                        <div className="min-w-0">
                                                            <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
                                                                <CardTitle
                                                                    as="h2"
                                                                    id={`workspace-${workspace.workspace.id}-title`}
                                                                    className="truncate"
                                                                    title={
                                                                        workspace
                                                                            .workspace
                                                                            .name
                                                                    }
                                                                >
                                                                    {
                                                                        workspace
                                                                            .workspace
                                                                            .name
                                                                    }
                                                                </CardTitle>
                                                                <Badge
                                                                    variant="secondary"
                                                                    className="text-foreground"
                                                                >
                                                                    {
                                                                        workspace
                                                                            .effectivePlan
                                                                            .name
                                                                    }
                                                                </Badge>
                                                                <span className="text-xs text-muted-foreground">
                                                                    Via{' '}
                                                                    {humanizeAdminValue(
                                                                        workspace
                                                                            .effectivePlan
                                                                            .source,
                                                                    )}
                                                                </span>
                                                            </div>
                                                            <CardDescription>
                                                                {workspace
                                                                    .workspace
                                                                    .owner
                                                                    ?.name ??
                                                                    'No owner'}{' '}
                                                                ·{' '}
                                                                {workspace
                                                                    .workspace
                                                                    .owner
                                                                    ?.email ??
                                                                    '—'}{' '}
                                                                ·{' '}
                                                                {
                                                                    workspace
                                                                        .workspace
                                                                        .membersCount
                                                                }{' '}
                                                                {workspace
                                                                    .workspace
                                                                    .membersCount ===
                                                                1
                                                                    ? 'member'
                                                                    : 'members'}
                                                            </CardDescription>
                                                        </div>
                                                    </div>
                                                    {canManage && (
                                                        <div className="flex flex-wrap gap-1.5">
                                                            {workspace.provider
                                                                .canCancel && (
                                                                <Button
                                                                    type="button"
                                                                    size="sm"
                                                                    variant="destructive"
                                                                    className="focus-visible:ring-destructive/40 max-lg:min-h-11"
                                                                    aria-label={`Cancel ${workspace.workspace.name} at period end`}
                                                                    onClick={() =>
                                                                        providerAction(
                                                                            workspace,
                                                                            'cancel',
                                                                        )
                                                                    }
                                                                >
                                                                    <Ban data-icon="inline-start" />
                                                                    Cancel at
                                                                    period end
                                                                </Button>
                                                            )}
                                                            {workspace.provider
                                                                .canResume && (
                                                                <Button
                                                                    type="button"
                                                                    size="sm"
                                                                    variant="outline"
                                                                    className="max-lg:min-h-11"
                                                                    aria-label={`Resume ${workspace.workspace.name}`}
                                                                    onClick={() =>
                                                                        providerAction(
                                                                            workspace,
                                                                            'resume',
                                                                        )
                                                                    }
                                                                >
                                                                    <RotateCcw data-icon="inline-start" />
                                                                    Resume
                                                                </Button>
                                                            )}
                                                            <Button
                                                                type="button"
                                                                size="sm"
                                                                variant="outline"
                                                                className="max-lg:min-h-11"
                                                                aria-label={`Add override for ${workspace.workspace.name}`}
                                                                onClick={(
                                                                    event,
                                                                ) =>
                                                                    openOverride(
                                                                        workspace,
                                                                        event.currentTarget,
                                                                    )
                                                                }
                                                            >
                                                                <Plus data-icon="inline-start" />
                                                                Add override
                                                            </Button>
                                                        </div>
                                                    )}
                                                </div>
                                            </CardHeader>
                                            <CardContent className="grid gap-2">
                                                <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted-foreground">
                                                    {workspace.effectivePlan
                                                        .overrideExpiresAt && (
                                                        <span
                                                            title={formatAdminDate(
                                                                workspace
                                                                    .effectivePlan
                                                                    .overrideExpiresAt,
                                                            )}
                                                        >
                                                            Expires{' '}
                                                            <span className="font-medium text-foreground tabular-nums">
                                                                {formatAdminShortDate(
                                                                    workspace
                                                                        .effectivePlan
                                                                        .overrideExpiresAt,
                                                                )}
                                                            </span>
                                                        </span>
                                                    )}
                                                    <span className="inline-flex items-center gap-1.5">
                                                        Provider
                                                        <Badge
                                                            variant={
                                                                provider.variant
                                                            }
                                                        >
                                                            {provider.label}
                                                        </Badge>
                                                    </span>
                                                    {workspace.provider
                                                        .currentPeriodEnd && (
                                                        <span
                                                            title={formatAdminDate(
                                                                workspace
                                                                    .provider
                                                                    .currentPeriodEnd,
                                                            )}
                                                        >
                                                            Renews{' '}
                                                            <span className="font-medium text-foreground tabular-nums">
                                                                {formatAdminShortDate(
                                                                    workspace
                                                                        .provider
                                                                        .currentPeriodEnd,
                                                                )}
                                                            </span>
                                                        </span>
                                                    )}
                                                    {workspace.provider
                                                        .endsAt && (
                                                        <span
                                                            title={formatAdminDate(
                                                                workspace
                                                                    .provider
                                                                    .endsAt,
                                                            )}
                                                        >
                                                            Cancels{' '}
                                                            <span className="font-medium text-foreground tabular-nums">
                                                                {formatAdminShortDate(
                                                                    workspace
                                                                        .provider
                                                                        .endsAt,
                                                                )}
                                                            </span>
                                                        </span>
                                                    )}
                                                    {workspace.provider
                                                        .priceId && (
                                                        <span>
                                                            {workspace.provider.name === 'paypal' ? 'PayPal plan ' : 'Stripe price '}
                                                            <span className="font-mono text-[11px] font-medium text-foreground">
                                                                {
                                                                    workspace
                                                                        .provider
                                                                        .priceId
                                                                }
                                                            </span>
                                                        </span>
                                                    )}
                                                </div>

                                                {workspace.overrides.length >
                                                    0 && (
                                                    <div className="grid gap-1">
                                                        <p className="text-xs font-medium text-muted-foreground">
                                                            Overrides (
                                                            {
                                                                workspace
                                                                    .overrides
                                                                    .length
                                                            }
                                                            )
                                                        </p>
                                                        <OverrideList
                                                            workspace={
                                                                workspace
                                                            }
                                                            canManage={
                                                                canManage
                                                            }
                                                            onRevoke={
                                                                openRevoke
                                                            }
                                                            variant="card"
                                                        />
                                                    </div>
                                                )}
                                            </CardContent>
                                        </Card>
                                    );
                                })}
                            </div>
                        </>
                    )}
                </div>

                {workspaces.total > 0 && (
                    <footer className="flex flex-col gap-3 text-sm text-muted-foreground sm:flex-row sm:items-center sm:justify-between">
                        <p aria-live="polite">
                            Showing {workspaces.from ?? 0}–{workspaces.to ?? 0}{' '}
                            of {workspaces.total.toLocaleString()} workspaces
                        </p>
                        {workspaces.last_page > 1 && (
                            <nav
                                className="flex flex-wrap gap-1"
                                aria-label="Subscriptions pagination"
                            >
                                {workspaces.links.map((link) =>
                                    link.url === null ? (
                                        <span
                                            key={link.label}
                                            className="rounded-md border px-3 py-2 opacity-50"
                                        >
                                            {link.label
                                                .replace('&laquo;', '‹')
                                                .replace('&raquo;', '›')}
                                        </span>
                                    ) : (
                                        <Link
                                            key={link.label}
                                            href={link.url}
                                            preserveScroll
                                            className={`min-h-9 rounded-md border px-3 py-2 focus-visible:ring-2 focus-visible:ring-ring/40 focus-visible:outline-none ${
                                                link.active
                                                    ? 'bg-primary text-primary-foreground'
                                                    : 'hover:bg-muted'
                                            }`}
                                        >
                                            {link.label
                                                .replace('&laquo;', '‹')
                                                .replace('&raquo;', '›')}
                                        </Link>
                                    ),
                                )}
                            </nav>
                        )}
                    </footer>
                )}
            </div>

            <OverrideDialog
                key={overrideTarget?.workspace.id ?? 'none'}
                workspace={overrideTarget}
                plans={plans}
                sources={sources}
                onClose={closeOverrideDialog}
            />

            <RevokeOverrideDialog
                key={
                    revokeTarget
                        ? `${revokeTarget.workspace.workspace.id}-${revokeTarget.override.id}`
                        : 'none'
                }
                target={revokeTarget}
                onClose={closeRevokeDialog}
            />

            <AdminConfirmDialog
                confirmation={pending?.confirmation ?? null}
                processing={processing}
                onCancel={() => setPending(null)}
                onConfirm={() => pending?.run()}
            />
        </>
    );
}

function OverrideList({
    workspace,
    canManage,
    onRevoke,
    variant,
}: {
    workspace: AdminSubscriptionRow;
    canManage: boolean;
    onRevoke: (
        workspace: AdminSubscriptionRow,
        override: AdminOverride,
        trigger: HTMLButtonElement,
    ) => void;
    variant: 'card' | 'table';
}) {
    return (
        <ul className="divide-y divide-separator">
            {workspace.overrides.map((override) => (
                <li
                    key={override.id}
                    className={
                        variant === 'table'
                            ? 'flex items-start justify-between gap-2 py-1.5 first:pt-0 last:pb-0'
                            : 'flex flex-col gap-1 py-2 first:pt-0 last:pb-0 sm:flex-row sm:items-center sm:justify-between'
                    }
                >
                    <div className="min-w-0">
                        <p className="text-xs font-medium text-foreground">
                            {override.plan?.name ?? 'Deleted plan'}
                            <span className="font-normal text-muted-foreground">
                                {' '}
                                via {humanizeAdminValue(override.source)}
                            </span>
                        </p>
                        <p
                            className="truncate text-xs text-muted-foreground"
                            title={override.reason}
                        >
                            {override.reason}
                        </p>
                    </div>
                    <div className="flex shrink-0 items-center gap-1">
                        <span
                            className="text-xs whitespace-nowrap text-muted-foreground tabular-nums"
                            title={`Started ${formatAdminDate(override.startsAt)}${override.expiresAt ? ` · Expires ${formatAdminDate(override.expiresAt)}` : ''}`}
                        >
                            {override.expiresAt
                                ? `Expires ${formatAdminShortDate(override.expiresAt)}`
                                : 'No expiry'}
                        </span>
                        {canManage &&
                            (variant === 'table' ? (
                                <Button
                                    type="button"
                                    size="icon"
                                    variant="destructive"
                                    className="size-8 focus-visible:ring-destructive/40"
                                    title="Revoke override"
                                    aria-label={`Revoke ${override.plan?.name ?? 'plan'} override for ${workspace.workspace.name}`}
                                    onClick={(event) =>
                                        onRevoke(
                                            workspace,
                                            override,
                                            event.currentTarget,
                                        )
                                    }
                                >
                                    <Trash2 aria-hidden="true" />
                                </Button>
                            ) : (
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="destructive"
                                    className="h-8 px-2 text-xs focus-visible:ring-destructive/40 max-lg:min-h-11"
                                    aria-label={`Revoke ${override.plan?.name ?? 'plan'} override for ${workspace.workspace.name}`}
                                    onClick={(event) =>
                                        onRevoke(
                                            workspace,
                                            override,
                                            event.currentTarget,
                                        )
                                    }
                                >
                                    <Trash2 aria-hidden="true" />
                                    Revoke
                                </Button>
                            ))}
                    </div>
                </li>
            ))}
        </ul>
    );
}

function OverrideDialog({
    workspace,
    plans,
    sources,
    onClose,
}: {
    workspace: AdminSubscriptionRow | null;
    plans: Array<{ code: string; name: string }>;
    sources: string[];
    onClose: () => void;
}) {
    const defaultSource = sources.includes('support_grant')
        ? 'support_grant'
        : (sources[0] ?? 'support_grant');
    const form = useForm({
        plan: '',
        source: defaultSource,
        reason: '',
        expires_at: '',
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        if (!workspace) {
            return;
        }

        form.post(subscriptions.overrides.store(workspace.workspace.id).url, {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                onClose();
            },
            onError: (errors) => {
                const first = Object.keys(errors)[0];
                const fieldId =
                    first === 'plan'
                        ? 'override-plan'
                        : first === 'source'
                          ? 'override-source'
                          : first === 'reason'
                            ? 'override-reason'
                            : first === 'expires_at'
                              ? 'override-expiry'
                              : null;

                if (fieldId) {
                    document.getElementById(fieldId)?.focus();
                }
            },
        });
    }

    return (
        <Dialog
            open={workspace !== null}
            onOpenChange={(open) => {
                if (!open) {
                    onClose();
                }
            }}
        >
            <DialogContent className="[&>button.absolute]:max-lg:top-3 [&>button.absolute]:max-lg:right-3 [&>button.absolute]:max-lg:size-11">
                <DialogHeader>
                    <DialogTitle>Add override</DialogTitle>
                    <DialogDescription>
                        {workspace
                            ? `Create a reasoned override for ${workspace.workspace.name}. If it expires, the workspace falls back to its paid plan, then Free.`
                            : 'Select a workspace.'}
                    </DialogDescription>
                </DialogHeader>
                <form className="grid gap-3" onSubmit={submit}>
                    <div className="grid gap-2">
                        <Label htmlFor="override-plan">Active plan</Label>
                        <Select
                            value={form.data.plan}
                            onValueChange={(value) =>
                                form.setData('plan', value)
                            }
                        >
                            <SelectTrigger
                                id="override-plan"
                                className="min-h-11 w-full"
                                aria-invalid={!!form.errors.plan}
                                aria-describedby={
                                    form.errors.plan
                                        ? 'override-plan-error'
                                        : undefined
                                }
                            >
                                <SelectValue placeholder="Choose a plan" />
                            </SelectTrigger>
                            <SelectContent>
                                {plans.map((plan) => (
                                    <SelectItem
                                        key={plan.code}
                                        value={plan.code}
                                    >
                                        {plan.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {form.errors.plan && (
                            <p
                                id="override-plan-error"
                                role="alert"
                                className="text-sm text-destructive"
                            >
                                {form.errors.plan}
                            </p>
                        )}
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="override-source">Source</Label>
                        <Select
                            value={form.data.source}
                            onValueChange={(value) =>
                                form.setData('source', value)
                            }
                        >
                            <SelectTrigger
                                id="override-source"
                                className="min-h-11 w-full"
                                aria-invalid={!!form.errors.source}
                                aria-describedby={
                                    form.errors.source
                                        ? 'override-source-error'
                                        : undefined
                                }
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {sources.map((source) => (
                                    <SelectItem key={source} value={source}>
                                        {humanizeAdminValue(source)}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {form.errors.source && (
                            <p
                                id="override-source-error"
                                role="alert"
                                className="text-sm text-destructive"
                            >
                                {form.errors.source}
                            </p>
                        )}
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="override-reason">Reason</Label>
                        <Textarea
                            id="override-reason"
                            value={form.data.reason}
                            maxLength={500}
                            required
                            rows={3}
                            aria-invalid={!!form.errors.reason}
                            aria-describedby={
                                form.errors.reason
                                    ? 'override-reason-error'
                                    : undefined
                            }
                            onChange={(event) =>
                                form.setData('reason', event.target.value)
                            }
                        />
                        {form.errors.reason && (
                            <p
                                id="override-reason-error"
                                role="alert"
                                className="text-sm text-destructive"
                            >
                                {form.errors.reason}
                            </p>
                        )}
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="override-expiry">
                            Optional future expiry (UTC)
                        </Label>
                        <Input
                            id="override-expiry"
                            type="date"
                            className="h-11"
                            value={form.data.expires_at}
                            aria-invalid={!!form.errors.expires_at}
                            aria-describedby={
                                form.errors.expires_at
                                    ? 'override-expiry-error'
                                    : undefined
                            }
                            onChange={(event) =>
                                form.setData('expires_at', event.target.value)
                            }
                        />
                        {form.errors.expires_at && (
                            <p
                                id="override-expiry-error"
                                role="alert"
                                className="text-sm text-destructive"
                            >
                                {form.errors.expires_at}
                            </p>
                        )}
                    </div>
                    <DialogFooter className="gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            disabled={form.processing}
                            onClick={onClose}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            className="focus-visible:ring-ring/50"
                            disabled={form.processing}
                        >
                            {form.processing ? 'Saving…' : 'Create override'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function RevokeOverrideDialog({
    target,
    onClose,
}: {
    target: {
        workspace: AdminSubscriptionRow;
        override: AdminOverride;
    } | null;
    onClose: () => void;
}) {
    const form = useForm({ reason: '' });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        if (!target) {
            return;
        }

        form.patch(
            subscriptions.overrides.revoke({
                workspace: target.workspace.workspace.id,
                override: target.override.id,
            }).url,
            {
                preserveScroll: true,
                onSuccess: () => {
                    form.reset();
                    onClose();
                },
                onError: () => {
                    document.getElementById('revoke-reason')?.focus();
                },
            },
        );
    }

    return (
        <Dialog
            open={target !== null}
            onOpenChange={(open) => {
                if (!open) {
                    onClose();
                }
            }}
        >
            <DialogContent className="[&>button.absolute]:max-lg:top-3 [&>button.absolute]:max-lg:right-3 [&>button.absolute]:max-lg:size-11">
                <DialogHeader>
                    <DialogTitle>Revoke override</DialogTitle>
                    <DialogDescription>
                        {target
                            ? `Revoke ${target.override.plan?.name ?? 'the plan'} for ${target.workspace.workspace.name}. The reason is stored in the audit trail.`
                            : ''}
                    </DialogDescription>
                </DialogHeader>
                <form className="grid gap-3" onSubmit={submit}>
                    <div className="grid gap-2">
                        <Label htmlFor="revoke-reason">Reason</Label>
                        <Textarea
                            id="revoke-reason"
                            value={form.data.reason}
                            maxLength={500}
                            required
                            rows={3}
                            aria-invalid={!!form.errors.reason}
                            aria-describedby={
                                form.errors.reason
                                    ? 'revoke-reason-error'
                                    : undefined
                            }
                            onChange={(event) =>
                                form.setData('reason', event.target.value)
                            }
                        />
                        {form.errors.reason && (
                            <p
                                id="revoke-reason-error"
                                role="alert"
                                className="text-sm text-destructive"
                            >
                                {form.errors.reason}
                            </p>
                        )}
                    </div>
                    <DialogFooter className="gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            disabled={form.processing}
                            onClick={onClose}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            variant="destructive"
                            className="focus-visible:ring-destructive/40"
                            disabled={form.processing}
                        >
                            {form.processing ? 'Revoking…' : 'Revoke override'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
