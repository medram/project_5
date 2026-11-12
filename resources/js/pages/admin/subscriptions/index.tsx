import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';
import { CreditCard, Plus, ShieldAlert } from 'lucide-react';
import {
    AdminConfirmDialog,
    type AdminConfirmation,
} from '@/components/admin/admin-confirm-dialog';
import { formatAdminDate, humanizeAdminValue } from '@/components/admin/format';
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
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <header className="space-y-1">
                    <div className="flex items-center gap-2 text-sm text-muted-foreground">
                        <CreditCard className="size-4" aria-hidden="true" />
                        <span>Platform administration</span>
                    </div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Subscriptions and overrides
                    </h1>
                    <p className="max-w-3xl text-sm text-muted-foreground">
                        Effective entitlements, local overrides, and
                        provider-owned Cashier state. Provider cancellation and
                        resume call Stripe and are disabled while configuration
                        is incomplete. Payment data and Stripe secrets are never
                        exposed.
                    </p>
                </header>

                {!readiness.configured && (
                    <div className="flex flex-wrap items-center gap-2 rounded-lg border p-4 text-sm">
                        <ShieldAlert
                            className="size-4 text-amber-600"
                            aria-hidden="true"
                        />
                        <span>Stripe is not configured.</span>
                        <code className="text-xs">
                            Missing: {readiness.missing.join(', ')}
                        </code>
                    </div>
                )}

                <form
                    onSubmit={search}
                    className="flex flex-col gap-2 sm:flex-row sm:items-end"
                >
                    <div className="grid flex-1 gap-2">
                        <Label htmlFor="subscription-search">
                            Search workspaces or owners
                        </Label>
                        <Input
                            id="subscription-search"
                            value={query}
                            maxLength={100}
                            placeholder="Workspace name, owner name, or email"
                            onChange={(event) => setQuery(event.target.value)}
                        />
                    </div>
                    <Button
                        type="submit"
                        variant="outline"
                        className="min-h-11"
                    >
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

                <div className="grid gap-4">
                    {workspaces.data.length === 0 && (
                        <Card>
                            <CardContent className="p-8 text-center text-sm text-muted-foreground">
                                No workspaces match this search.
                            </CardContent>
                        </Card>
                    )}
                    {workspaces.data.map((workspace) => (
                        <Card key={workspace.workspace.id}>
                            <CardHeader className="gap-2">
                                <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                    <div>
                                        <CardTitle>
                                            {workspace.workspace.name}
                                        </CardTitle>
                                        <CardDescription>
                                            {workspace.workspace.owner?.name ??
                                                'No owner'}{' '}
                                            ·{' '}
                                            {workspace.workspace.owner?.email ??
                                                '—'}{' '}
                                            · {workspace.workspace.membersCount}{' '}
                                            members
                                        </CardDescription>
                                    </div>
                                    <Badge variant="outline">
                                        {workspace.effectivePlan.name} ·{' '}
                                        {humanizeAdminValue(
                                            workspace.effectivePlan.source,
                                        )}
                                    </Badge>
                                </div>
                            </CardHeader>
                            <CardContent className="grid gap-4">
                                <div className="grid gap-3 text-sm sm:grid-cols-2 xl:grid-cols-4">
                                    <Detail
                                        label="Effective plan"
                                        value={workspace.effectivePlan.name}
                                    />
                                    <Detail
                                        label="Override expires"
                                        value={formatAdminDate(
                                            workspace.effectivePlan
                                                .overrideExpiresAt,
                                        )}
                                    />
                                    <Detail
                                        label="Provider status"
                                        value={
                                            workspace.provider.status
                                                ? humanizeAdminValue(
                                                      workspace.provider.status,
                                                  )
                                                : 'No provider subscription'
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
                                </div>

                                <div className="flex flex-wrap gap-2">
                                    {canManage && (
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="outline"
                                            className="min-h-9"
                                            disabled={
                                                !workspace.provider.canCancel
                                            }
                                            onClick={() =>
                                                providerAction(
                                                    workspace,
                                                    'cancel',
                                                )
                                            }
                                        >
                                            Cancel at period end
                                        </Button>
                                    )}
                                    {canManage && (
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="outline"
                                            className="min-h-9"
                                            disabled={
                                                !workspace.provider.canResume
                                            }
                                            onClick={() =>
                                                providerAction(
                                                    workspace,
                                                    'resume',
                                                )
                                            }
                                        >
                                            Resume
                                        </Button>
                                    )}
                                    {canManage && (
                                        <Button
                                            type="button"
                                            size="sm"
                                            className="min-h-9"
                                            onClick={() =>
                                                setOverrideTarget(workspace)
                                            }
                                        >
                                            <Plus aria-hidden="true" />
                                            Add override
                                        </Button>
                                    )}
                                </div>

                                {workspace.overrides.length > 0 && (
                                    <div className="grid gap-2">
                                        <p className="text-sm font-medium">
                                            Active overrides
                                        </p>
                                        {workspace.overrides.map((override) => (
                                            <div
                                                key={override.id}
                                                className="flex flex-col gap-2 rounded-md border px-3 py-2 text-sm sm:flex-row sm:items-center sm:justify-between"
                                            >
                                                <div className="min-w-0">
                                                    <p className="font-medium">
                                                        {override.plan?.name ??
                                                            'Deleted plan'}{' '}
                                                        ·{' '}
                                                        {humanizeAdminValue(
                                                            override.source,
                                                        )}
                                                    </p>
                                                    <p className="text-xs text-muted-foreground">
                                                        {override.reason}
                                                    </p>
                                                    <p className="text-xs text-muted-foreground">
                                                        {formatAdminDate(
                                                            override.startsAt,
                                                        )}{' '}
                                                        →{' '}
                                                        {formatAdminDate(
                                                            override.expiresAt,
                                                        )}
                                                    </p>
                                                </div>
                                                {canManage && (
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="outline"
                                                        className="min-h-9 shrink-0"
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
                        <nav className="flex flex-wrap gap-1">
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
        <div>
            <p className="text-xs text-muted-foreground">{label}</p>
            <p className="break-words">{value}</p>
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
