import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';
import { Ban, Plus, RotateCcw, Search } from 'lucide-react';
import {
    AdminConfirmDialog,
    type AdminConfirmation,
} from '@/components/admin/admin-confirm-dialog';
import { formatAdminDate, humanizeAdminValue } from '@/components/admin/format';
import { PageHeader } from '@/components/page-header';
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
import subscriptions from '@/routes/admin/subscriptions';
import type {
    AdminOverride,
    AdminSubscriptionRow,
    AdminSubscriptionsPageProps,
} from '@/types/admin-billing';

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

    function search(event: FormEvent) {
        event.preventDefault();
        router.get(
            subscriptions.index().url,
            { q: query.trim() || undefined },
            { preserveScroll: true },
        );
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
                        ? 'Stripe cancels at the end of the current billing period. Local status and renewal dates are not edited directly.'
                        : 'Stripe resumes the subscription within its current grace period.',
                confirmLabel:
                    action === 'cancel'
                        ? 'Cancel at period end'
                        : 'Resume subscription',
                destructive: action === 'cancel',
            },
            run: () => {
                setProcessing(true);
                router.post(
                    action === 'cancel'
                        ? subscriptions.cancel(workspace.workspace.id).url
                        : subscriptions.resume(workspace.workspace.id).url,
                    {},
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
            <div className="mx-auto flex w-full max-w-7xl min-w-0 flex-1 flex-col gap-5 px-5 pt-4 pb-10">
                <PageHeader
                    title="Subscriptions and overrides"
                    description="Effective entitlements, local overrides, and provider-owned billing state. Payment data and provider secrets are never exposed."
                />

                {!readiness.configured && (
                    <details className="rounded-xl border bg-card px-4 py-3 text-sm">
                        <summary className="cursor-pointer text-muted-foreground">
                            Provider billing is not configured — cancellation
                            and resume are disabled
                        </summary>
                        <p className="mt-2 font-mono text-xs text-muted-foreground">
                            {readiness.missing.join(', ')}
                        </p>
                    </details>
                )}

                <form
                    onSubmit={search}
                    className="flex items-center gap-2 rounded-xl border bg-card p-3"
                >
                    <Input
                        id="subscription-search"
                        value={query}
                        maxLength={100}
                        placeholder="Search workspace, owner, or email"
                        aria-label="Search subscriptions"
                        className="h-9 max-w-sm"
                        onChange={(event) => setQuery(event.target.value)}
                    />
                    <Button type="submit" size="sm" className="h-9">
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

                <div className="grid gap-3">
                    {workspaces.data.length === 0 && (
                        <Card variant="transparent">
                            <CardContent className="p-8 text-center text-sm text-muted-foreground">
                                No workspaces match this search.
                            </CardContent>
                        </Card>
                    )}
                    {workspaces.data.map((workspace) => (
                        <Card
                            key={workspace.workspace.id}
                            className="gap-2 py-3"
                        >
                            <CardHeader className="gap-1">
                                <div className="flex flex-wrap items-start justify-between gap-x-4 gap-y-2">
                                    <div className="min-w-0">
                                        <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
                                            <CardTitle className="truncate">
                                                {workspace.workspace.name}
                                            </CardTitle>
                                            <Badge
                                                variant="outline"
                                                className="font-normal"
                                            >
                                                {workspace.effectivePlan.name}
                                            </Badge>
                                            <span className="text-xs text-muted-foreground">
                                                {humanizeAdminValue(
                                                    workspace.effectivePlan
                                                        .source,
                                                ).toLowerCase()}
                                            </span>
                                        </div>
                                        <CardDescription className="truncate">
                                            {workspace.workspace.owner?.name ??
                                                'No owner'}{' '}
                                            ·{' '}
                                            {workspace.workspace.owner?.email ??
                                                '—'}{' '}
                                            · {workspace.workspace.membersCount}{' '}
                                            {workspace.workspace
                                                .membersCount === 1
                                                ? 'member'
                                                : 'members'}
                                        </CardDescription>
                                    </div>
                                    {canManage && (
                                        <div className="flex flex-wrap gap-1.5">
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                disabled={
                                                    !workspace.provider
                                                        .canCancel
                                                }
                                                onClick={() =>
                                                    providerAction(
                                                        workspace,
                                                        'cancel',
                                                    )
                                                }
                                            >
                                                <Ban data-icon="inline-start" />
                                                Cancel at period end
                                            </Button>
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                disabled={
                                                    !workspace.provider
                                                        .canResume
                                                }
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
                                            <Button
                                                type="button"
                                                size="sm"
                                                onClick={() =>
                                                    setOverrideTarget(workspace)
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
                                <dl className="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs">
                                    <Detail
                                        label="Override expires"
                                        value={formatAdminDate(
                                            workspace.effectivePlan
                                                .overrideExpiresAt,
                                        )}
                                    />
                                    <Detail
                                        label="Provider"
                                        value={
                                            workspace.provider.status
                                                ? humanizeAdminValue(
                                                      workspace.provider.status,
                                                  )
                                                : 'No subscription'
                                        }
                                    />
                                    <Detail
                                        label="Period end"
                                        value={formatAdminDate(
                                            workspace.provider.currentPeriodEnd,
                                        )}
                                    />
                                    <Detail
                                        label="Cancellation end"
                                        value={formatAdminDate(
                                            workspace.provider.endsAt,
                                        )}
                                    />
                                    <Detail
                                        label="Stripe price"
                                        value={
                                            workspace.provider.priceId ?? '—'
                                        }
                                    />
                                </dl>

                                {workspace.overrides.length > 0 && (
                                    <div className="grid gap-1">
                                        <p className="text-xs font-medium text-muted-foreground">
                                            Active overrides
                                        </p>
                                        {workspace.overrides.map((override) => (
                                            <div
                                                key={override.id}
                                                className="flex flex-col gap-1 rounded-md border border-separator px-2.5 py-1.5 text-xs sm:flex-row sm:items-center sm:justify-between"
                                            >
                                                <div className="min-w-0">
                                                    <div className="flex flex-wrap items-baseline gap-x-1.5 gap-y-0.5">
                                                        <span className="font-medium text-foreground">
                                                            {override.plan
                                                                ?.name ??
                                                                'Deleted plan'}
                                                        </span>
                                                        <span className="text-muted-foreground">
                                                            ·{' '}
                                                            {humanizeAdminValue(
                                                                override.source,
                                                            )}
                                                        </span>
                                                        <span
                                                            className="truncate text-muted-foreground"
                                                            title={
                                                                override.reason
                                                            }
                                                        >
                                                            · {override.reason}
                                                        </span>
                                                        <span className="text-muted-foreground tabular-nums">
                                                            ·{' '}
                                                            {formatAdminDate(
                                                                override.startsAt,
                                                            )}{' '}
                                                            →{' '}
                                                            {formatAdminDate(
                                                                override.expiresAt,
                                                            )}
                                                        </span>
                                                    </div>
                                                </div>
                                                {canManage && (
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="ghost"
                                                        className="h-8 shrink-0 self-start px-2 text-xs sm:self-auto"
                                                        onClick={() =>
                                                            setRevokeTarget({
                                                                workspace,
                                                                override,
                                                            })
                                                        }
                                                    >
                                                        Revoke
                                                    </Button>
                                                )}
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <footer className="flex flex-col gap-3 text-sm text-muted-foreground sm:flex-row sm:items-center sm:justify-between">
                    <p>
                        Showing {workspaces.from ?? 0}–{workspaces.to ?? 0} of{' '}
                        {workspaces.total.toLocaleString()} workspaces
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
                                        className={`min-h-9 rounded-md border px-3 py-2 ${
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
            </div>

            <OverrideDialog
                workspace={overrideTarget}
                plans={plans}
                sources={sources}
                onClose={() => setOverrideTarget(null)}
            />

            <RevokeOverrideDialog
                target={revokeTarget}
                onClose={() => setRevokeTarget(null)}
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

function Detail({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex items-baseline gap-1.5">
            <dt className="text-muted-foreground">{label}</dt>
            <dd className="font-medium break-words tabular-nums">{value}</dd>
        </div>
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
    const form = useForm({
        plan: '',
        source: sources[0] ?? 'support_grant',
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
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Add plan override</DialogTitle>
                    <DialogDescription>
                        {workspace
                            ? `Create a reasoned override for ${workspace.workspace.name}. An expiry falls through to Cashier, then Free.`
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
                            <p className="text-sm text-destructive">
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
                            <p className="text-sm text-destructive">
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
                            onChange={(event) =>
                                form.setData('reason', event.target.value)
                            }
                        />
                        {form.errors.reason && (
                            <p className="text-sm text-destructive">
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
                            value={form.data.expires_at}
                            onChange={(event) =>
                                form.setData('expires_at', event.target.value)
                            }
                        />
                        {form.errors.expires_at && (
                            <p className="text-sm text-destructive">
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
                        <Button type="submit" disabled={form.processing}>
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
            <DialogContent>
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
                            onChange={(event) =>
                                form.setData('reason', event.target.value)
                            }
                        />
                        {form.errors.reason && (
                            <p className="text-sm text-destructive">
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
