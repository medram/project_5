import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import {
    ArrowLeft,
    KeyRound,
    ShieldCheck,
    UserRoundX,
    Users,
} from 'lucide-react';
import {
    AccountStatusBadge,
    PlanBadge,
    RoleBadges,
    VerificationBadge,
} from '@/components/admin/admin-badges';
import {
    AdminConfirmDialog,
    type AdminConfirmation,
} from '@/components/admin/admin-confirm-dialog';
import {
    formatAdminDate,
    formatAdminDay,
    humanizeAdminValue,
} from '@/components/admin/format';
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
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import users from '@/routes/admin/users';
import { update as updateSuspension } from '@/routes/admin/users/suspension';
import {
    resend as resendVerification,
    update as updateVerification,
} from '@/routes/admin/users/verification';
import { destroy as revokeSessions } from '@/routes/admin/users/sessions';
import { destroy as revokeTokens } from '@/routes/admin/users/tokens';
import { update as updateRole } from '@/routes/admin/users/roles';
import {
    restore as restoreDeletion,
    schedule as scheduleDeletion,
} from '@/routes/admin/users/deletion';
import { update as updatePlan } from '@/routes/admin/users/plan';
import type { AdminUserDetailProps } from '@/types/admin';

type PendingAction = {
    confirmation: AdminConfirmation;
    perform: () => void;
};

export default function AdminUserShow({
    account,
    security,
    workspaces,
    roles,
    plans,
}: AdminUserDetailProps) {
    const { auth } = usePage().props;
    const can = (permission: string) =>
        auth.adminAbilities.includes(permission);
    const [pending, setPending] = useState<PendingAction | null>(null);
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [planCode, setPlanCode] = useState('');

    function request(
        confirmation: AdminConfirmation,
        perform: () => void,
    ): void {
        setError(null);
        setPending({ confirmation, perform });
    }

    function finish(): void {
        setProcessing(false);
        setPending(null);
    }

    function fail(errors: Record<string, string>): void {
        setProcessing(false);
        setError(
            Object.values(errors)[0] ?? 'The action could not be completed.',
        );
    }

    function confirm(): void {
        if (!pending) {
            return;
        }

        setProcessing(true);
        pending.perform();
    }

    function setSuspension(suspended: boolean): void {
        request(
            {
                title: suspended
                    ? `Suspend ${account.name}?`
                    : `Restore ${account.name}?`,
                description: suspended
                    ? 'This blocks sign-in, clears sessions, and leaves public content in place. The action is recorded in the admin audit log.'
                    : 'This restores sign-in access for the account. The action is recorded in the admin audit log.',
                confirmLabel: suspended ? 'Suspend account' : 'Restore account',
                destructive: suspended,
            },
            () =>
                router.patch(
                    updateSuspension(account.id).url,
                    { suspended },
                    {
                        preserveScroll: true,
                        onSuccess: finish,
                        onError: fail,
                        onFinish: () => setProcessing(false),
                    },
                ),
        );
    }

    function setVerification(verified: boolean): void {
        request(
            {
                title: verified
                    ? `Mark ${account.email} verified?`
                    : `Mark ${account.email} unverified?`,
                description: verified
                    ? 'This records the account as email-verified without sending a message.'
                    : 'This clears the email verification timestamp. The account may be asked to verify again.',
                confirmLabel: verified ? 'Mark verified' : 'Mark unverified',
                destructive: !verified,
            },
            () =>
                router.patch(
                    updateVerification(account.id).url,
                    { verified },
                    {
                        preserveScroll: true,
                        onSuccess: finish,
                        onError: fail,
                        onFinish: () => setProcessing(false),
                    },
                ),
        );
    }

    function resend(): void {
        request(
            {
                title: 'Resend verification email?',
                description: `Send a new verification link to ${account.email}.`,
                confirmLabel: 'Send email',
            },
            () =>
                router.post(
                    resendVerification(account.id).url,
                    {},
                    {
                        preserveScroll: true,
                        onSuccess: finish,
                        onError: fail,
                        onFinish: () => setProcessing(false),
                    },
                ),
        );
    }

    function revokeAccountSessions(): void {
        request(
            {
                title: 'Revoke sessions?',
                description:
                    'This signs the account out of every browser session. It does not revoke API tokens.',
                confirmLabel: 'Revoke sessions',
                destructive: true,
            },
            () =>
                router.delete(revokeSessions(account.id).url, {
                    preserveScroll: true,
                    onSuccess: finish,
                    onError: fail,
                    onFinish: () => setProcessing(false),
                }),
        );
    }

    function revokeAccountTokens(): void {
        request(
            {
                title: 'Revoke API tokens?',
                description:
                    'This deletes every workspace API token issued to this account. Existing integrations will stop authenticating.',
                confirmLabel: 'Revoke tokens',
                destructive: true,
            },
            () =>
                router.delete(revokeTokens(account.id).url, {
                    preserveScroll: true,
                    onSuccess: finish,
                    onError: fail,
                    onFinish: () => setProcessing(false),
                }),
        );
    }

    function changeRole(code: string, assigned: boolean): void {
        const roleName = roles.find((role) => role.code === code)?.name ?? code;

        request(
            {
                title: assigned ? `Grant ${roleName}?` : `Remove ${roleName}?`,
                description: assigned
                    ? `${account.name} will receive the permissions in this immutable role.`
                    : `${account.name} will lose the permissions granted by this role.`,
                confirmLabel: assigned ? 'Grant role' : 'Remove role',
                destructive: !assigned,
            },
            () =>
                router.patch(
                    updateRole(account.id).url,
                    { role: code, assigned },
                    {
                        preserveScroll: true,
                        onSuccess: finish,
                        onError: fail,
                        onFinish: () => setProcessing(false),
                    },
                ),
        );
    }

    function scheduleAccountDeletion(): void {
        request(
            {
                title: 'Schedule account deletion?',
                description:
                    'This starts the restorable 30-day deletion lifecycle, revokes credentials, and schedules a purge. Subscription, shared-workspace, and last-super-admin safeguards still apply.',
                confirmLabel: 'Schedule deletion',
                destructive: true,
            },
            () =>
                router.patch(
                    scheduleDeletion(account.id).url,
                    {},
                    {
                        preserveScroll: true,
                        onSuccess: finish,
                        onError: fail,
                        onFinish: () => setProcessing(false),
                    },
                ),
        );
    }

    function restoreAccountDeletion(): void {
        request(
            {
                title: 'Restore this account?',
                description:
                    'This clears the pending deletion and restores the account before the purge deadline.',
                confirmLabel: 'Restore account',
            },
            () =>
                router.patch(
                    restoreDeletion(account.id).url,
                    {},
                    {
                        preserveScroll: true,
                        onSuccess: finish,
                        onError: fail,
                        onFinish: () => setProcessing(false),
                    },
                ),
        );
    }

    function assignPlan(): void {
        const plan = plans.find((candidate) => candidate.code === planCode);

        if (!plan) {
            return;
        }

        request(
            {
                title: `Assign ${plan.name}?`,
                description:
                    'This records a local platform override for the personal workspace. Provider billing remains untouched.',
                confirmLabel: `Assign ${plan.name}`,
            },
            () =>
                router.patch(
                    updatePlan(account.id).url,
                    { plan: plan.code },
                    {
                        preserveScroll: true,
                        onSuccess: finish,
                        onError: fail,
                        onFinish: () => setProcessing(false),
                    },
                ),
        );
    }

    return (
        <>
            <Head title={`${account.name} — Administration`} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <header className="space-y-4">
                    <Button asChild variant="ghost" size="sm" className="px-0">
                        <Link href={users.index().url}>
                            <ArrowLeft aria-hidden="true" />
                            All users
                        </Link>
                    </Button>
                    <div className="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                        <div className="flex min-w-0 items-center gap-4">
                            <AccountAvatar account={account} />
                            <div className="min-w-0 space-y-1">
                                <h1 className="truncate text-2xl font-semibold tracking-tight">
                                    {account.name}
                                </h1>
                                <p className="truncate text-sm text-muted-foreground">
                                    {account.email}
                                </p>
                                <div className="flex flex-wrap gap-2 pt-1">
                                    <AccountStatusBadge
                                        status={account.status}
                                    />
                                    <VerificationBadge
                                        verified={account.emailVerified}
                                    />
                                    <RoleBadges roles={account.roles} />
                                </div>
                            </div>
                        </div>
                        {!account.isSelf && (
                            <div className="flex flex-wrap gap-2">
                                {can('admin.users.manage_status') && (
                                    <Button
                                        type="button"
                                        variant={
                                            account.status === 'suspended'
                                                ? 'outline'
                                                : 'destructive'
                                        }
                                        className="min-h-11"
                                        onClick={() =>
                                            setSuspension(
                                                account.status !== 'suspended',
                                            )
                                        }
                                    >
                                        <UserRoundX aria-hidden="true" />
                                        {account.status === 'suspended'
                                            ? 'Restore access'
                                            : 'Suspend account'}
                                    </Button>
                                )}
                                {can('admin.users.schedule_deletion') &&
                                    (account.status === 'deletion_pending' ? (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            className="min-h-11"
                                            onClick={restoreAccountDeletion}
                                        >
                                            Restore deletion
                                        </Button>
                                    ) : (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            className="min-h-11"
                                            onClick={scheduleAccountDeletion}
                                        >
                                            Schedule deletion
                                        </Button>
                                    ))}
                            </div>
                        )}
                    </div>
                </header>

                {error && (
                    <p
                        role="alert"
                        className="rounded-md border border-destructive/40 bg-destructive/10 px-4 py-3 text-sm text-destructive"
                    >
                        {error}
                    </p>
                )}

                {account.status === 'deletion_pending' && (
                    <div className="rounded-md border border-amber-500/40 bg-amber-500/10 px-4 py-3 text-sm">
                        Deletion requested{' '}
                        {formatAdminDate(account.deletionRequestedAt)}. Purge is
                        scheduled after {formatAdminDay(account.purgeAfter)}.
                    </div>
                )}

                <section className="grid gap-4 xl:grid-cols-[minmax(0,1.25fr)_minmax(0,1fr)]">
                    <Card>
                        <CardHeader className="gap-1">
                            <CardTitle>General</CardTitle>
                            <CardDescription>
                                Account identity and lifecycle metadata.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <dl className="grid gap-4 sm:grid-cols-2">
                                <Field
                                    label="Account ID"
                                    value={String(account.id)}
                                />
                                <Field
                                    label="Email verification"
                                    value={
                                        account.emailVerifiedAt
                                            ? formatAdminDate(
                                                  account.emailVerifiedAt,
                                              )
                                            : 'Unverified'
                                    }
                                />
                                <Field
                                    label="Last seen"
                                    value={formatAdminDate(account.lastSeenAt)}
                                />
                                <Field
                                    label="Created"
                                    value={formatAdminDate(account.createdAt)}
                                />
                                <Field
                                    label="Suspended"
                                    value={
                                        account.suspendedAt
                                            ? formatAdminDate(
                                                  account.suspendedAt,
                                              )
                                            : 'No'
                                    }
                                />
                                <Field
                                    label="Updated"
                                    value={formatAdminDate(account.updatedAt)}
                                />
                            </dl>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="gap-1">
                            <CardTitle>Plan</CardTitle>
                            <CardDescription>
                                Entitlement source for the personal workspace.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-3">
                            <PlanBadge
                                plan={
                                    workspaces.find(
                                        (workspace) => workspace.isPersonal,
                                    )?.plan ?? null
                                }
                            />
                            {can('admin.billing.manage') && !account.isSelf && (
                                <div className="flex flex-col gap-2 sm:flex-row">
                                    <Select
                                        value={planCode}
                                        onValueChange={setPlanCode}
                                    >
                                        <SelectTrigger
                                            className="min-h-11 w-full"
                                            aria-label="Assign plan"
                                        >
                                            <SelectValue placeholder="Select a plan" />
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
                                    <Button
                                        type="button"
                                        className="min-h-11"
                                        disabled={!planCode}
                                        onClick={assignPlan}
                                    >
                                        Assign
                                    </Button>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </section>

                <Card>
                    <CardHeader className="gap-1">
                        <CardTitle className="flex items-center gap-2">
                            <Users aria-hidden="true" />
                            Workspaces
                        </CardTitle>
                        <CardDescription>
                            Membership role, effective entitlement, and resource
                            counts only. Destinations and tenant content are not
                            exposed.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {workspaces.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                This account has no workspaces.
                            </p>
                        ) : (
                            <div className="grid gap-3">
                                {workspaces.map((workspace) => (
                                    <div
                                        key={workspace.id}
                                        className="grid gap-3 rounded-lg border p-4 lg:grid-cols-[minmax(0,1fr)_repeat(4,auto)] lg:items-center"
                                    >
                                        <div className="min-w-0">
                                            <p className="truncate font-medium">
                                                {workspace.name}
                                            </p>
                                            <div className="flex flex-wrap gap-1 pt-1">
                                                <Badge variant="outline">
                                                    {humanizeAdminValue(
                                                        workspace.role,
                                                    )}
                                                </Badge>
                                                {workspace.isOwner &&
                                                    workspace.role !==
                                                        'owner' && (
                                                        <Badge variant="secondary">
                                                            Owner
                                                        </Badge>
                                                    )}
                                            </div>
                                        </div>
                                        <div>
                                            <p className="text-xs text-muted-foreground">
                                                Plan
                                            </p>
                                            <PlanBadge plan={workspace.plan} />
                                        </div>
                                        <Metric
                                            label="Members"
                                            value={workspace.membersCount}
                                        />
                                        <Metric
                                            label="Links"
                                            value={workspace.linksCount}
                                        />
                                        <Metric
                                            label="Pages"
                                            value={workspace.bioPagesCount}
                                        />
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>

                <section className="grid gap-4 xl:grid-cols-2">
                    <Card>
                        <CardHeader className="gap-1">
                            <CardTitle className="flex items-center gap-2">
                                <ShieldCheck aria-hidden="true" />
                                Platform roles
                            </CardTitle>
                            <CardDescription>
                                Immutable seeded roles. Role changes are audited
                                and cannot be performed on your own account.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-3">
                            {roles.map((role) => {
                                const assigned = account.roles.some(
                                    (current) => current.code === role.code,
                                );

                                return (
                                    <div
                                        key={role.code}
                                        className="rounded-lg border p-4"
                                    >
                                        <div className="flex items-start justify-between gap-3">
                                            <div>
                                                <p className="font-medium">
                                                    {role.name}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {role.permissions.length}{' '}
                                                    permissions
                                                </p>
                                            </div>
                                            {can('admin.users.manage_roles') &&
                                                !account.isSelf && (
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant={
                                                            assigned
                                                                ? 'outline'
                                                                : 'default'
                                                        }
                                                        className="min-h-9"
                                                        onClick={() =>
                                                            changeRole(
                                                                role.code,
                                                                !assigned,
                                                            )
                                                        }
                                                    >
                                                        {assigned
                                                            ? 'Remove'
                                                            : 'Grant'}
                                                    </Button>
                                                )}
                                        </div>
                                        <details className="mt-3">
                                            <summary className="cursor-pointer text-sm text-muted-foreground">
                                                Permissions
                                            </summary>
                                            <ul className="mt-2 grid gap-1 text-xs text-muted-foreground sm:grid-cols-2">
                                                {role.permissions.map(
                                                    (permission) => (
                                                        <li
                                                            key={
                                                                permission.value
                                                            }
                                                        >
                                                            {permission.label}
                                                        </li>
                                                    ),
                                                )}
                                            </ul>
                                        </details>
                                    </div>
                                );
                            })}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="gap-1">
                            <CardTitle className="flex items-center gap-2">
                                <KeyRound aria-hidden="true" />
                                Security
                            </CardTitle>
                            <CardDescription>
                                Credential metadata. Secrets and token values
                                are never shown.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4">
                            <dl className="grid gap-3 sm:grid-cols-2">
                                <Field
                                    label="Two-factor"
                                    value={
                                        security.twoFactorEnabled
                                            ? 'Enabled'
                                            : 'Not enabled'
                                    }
                                />
                                <Field
                                    label="Passkeys"
                                    value={String(security.passkeyCount)}
                                />
                                <Field
                                    label="Browser sessions"
                                    value={String(security.sessionCount)}
                                />
                                <Field
                                    label="API tokens"
                                    value={String(security.tokenCount)}
                                />
                                <Field
                                    label="Latest session"
                                    value={formatAdminDate(
                                        security.latestSessionAt,
                                    )}
                                />
                            </dl>

                            {!account.isSelf && (
                                <div className="flex flex-wrap gap-2">
                                    {can('admin.users.manage_verification') &&
                                        (account.emailVerified ? (
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                className="min-h-9"
                                                onClick={() =>
                                                    setVerification(false)
                                                }
                                            >
                                                Mark unverified
                                            </Button>
                                        ) : (
                                            <>
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant="outline"
                                                    className="min-h-9"
                                                    onClick={() =>
                                                        setVerification(true)
                                                    }
                                                >
                                                    Mark verified
                                                </Button>
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant="outline"
                                                    className="min-h-9"
                                                    onClick={resend}
                                                >
                                                    Resend verification
                                                </Button>
                                            </>
                                        ))}
                                    {can('admin.users.manage_status') && (
                                        <>
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                className="min-h-9"
                                                onClick={revokeAccountSessions}
                                            >
                                                Revoke sessions
                                            </Button>
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                className="min-h-9"
                                                onClick={revokeAccountTokens}
                                            >
                                                Revoke tokens
                                            </Button>
                                        </>
                                    )}
                                </div>
                            )}

                            {security.tokens.length > 0 && (
                                <div className="grid gap-2">
                                    <p className="text-sm font-medium">
                                        Recent API tokens
                                    </p>
                                    {security.tokens.map((token) => (
                                        <div
                                            key={token.id}
                                            className="flex flex-wrap items-center justify-between gap-2 rounded-md border px-3 py-2 text-xs"
                                        >
                                            <span className="font-medium">
                                                {token.name}
                                            </span>
                                            <span className="text-muted-foreground">
                                                Last used{' '}
                                                {formatAdminDate(
                                                    token.lastUsedAt,
                                                )}
                                            </span>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </section>
            </div>

            <AdminConfirmDialog
                confirmation={pending?.confirmation ?? null}
                processing={processing}
                onCancel={() => {
                    if (!processing) {
                        setPending(null);
                    }
                }}
                onConfirm={confirm}
            />
        </>
    );
}

function AccountAvatar({
    account,
}: {
    account: AdminUserDetailProps['account'];
}) {
    if (account.avatarUrl) {
        return (
            <img
                src={account.avatarUrl}
                alt=""
                className="size-16 shrink-0 rounded-full border object-cover"
            />
        );
    }

    return (
        <span
            className="flex size-16 shrink-0 items-center justify-center rounded-full border bg-muted text-lg font-medium uppercase"
            aria-hidden="true"
        >
            {account.name.slice(0, 2)}
        </span>
    );
}

function Field({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd className="break-words">{value}</dd>
        </div>
    );
}

function Metric({ label, value }: { label: string; value: number }) {
    return (
        <div>
            <p className="text-xs text-muted-foreground">{label}</p>
            <p className="font-medium tabular-nums">{value}</p>
        </div>
    );
}
