import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { type FormEvent, useEffect, useMemo, useState } from 'react';
import {
    Ban,
    Clock3,
    ExternalLink,
    Link2,
    Megaphone,
    Search,
    ShieldCheck,
    UsersRound,
} from 'lucide-react';
import {
    AccountStatusBadge,
    PlanBadge,
    RoleBadges,
    VerificationBadge,
} from '@/components/admin/admin-badges';
import { formatAdminDate } from '@/components/admin/format';
import { StatCard } from '@/components/dashboard/stat-card';
import { PageHeader } from '@/components/page-header';
import { UserAvatar } from '@/components/user-avatar';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
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
import users from '@/routes/admin/users';
import { broadcast as broadcastNotification } from '@/routes/admin/notifications';
import type {
    AdminUserFilters,
    AdminUserListItem,
    AdminUsersPageProps,
} from '@/types/admin';

const emptyFilters: AdminUserFilters = {
    q: '',
    status: null,
    verification: null,
    role: null,
    plan: null,
};

function paginationLabel(label: string): string {
    return label
        .replace('&laquo; Previous', 'Previous')
        .replace('Next &raquo;', 'Next')
        .replace('&laquo;', '‹')
        .replace('&raquo;', '›');
}

export default function AdminUsersIndex({
    users: paginator,
    filters,
    plans,
    roles,
    overview,
}: AdminUsersPageProps) {
    const { auth } = usePage().props;
    const canBroadcast = auth.adminAbilities.includes(
        'admin.notifications.broadcast',
    );
    const [filterState, setFilterState] =
        useState<AdminUserFilters>(emptyFilters);
    const [broadcastOpen, setBroadcastOpen] = useState(false);
    const broadcastForm = useForm({ title: '', body: '' });

    useEffect(() => {
        setFilterState({ ...emptyFilters, ...filters });
    }, [filters]);

    const activeFilterCount = useMemo(
        () =>
            Object.values(filterState).filter(
                (value) => value !== null && value !== '',
            ).length,
        [filterState],
    );

    function applyFilters(event?: FormEvent) {
        event?.preventDefault();
        router.get(
            users.index().url,
            {
                q: filterState.q.trim() || undefined,
                status: filterState.status || undefined,
                verification: filterState.verification || undefined,
                role: filterState.role || undefined,
                plan: filterState.plan || undefined,
            },
            { preserveScroll: true, preserveState: true },
        );
    }

    function resetFilters() {
        setFilterState(emptyFilters);
        router.get(users.index().url, {}, { preserveScroll: true });
    }

    function submitBroadcast(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        broadcastForm.post(broadcastNotification().url, {
            preserveScroll: true,
            onSuccess: () => {
                broadcastForm.reset();
                setBroadcastOpen(false);
            },
        });
    }

    return (
        <>
            <Head title="Users — Administration" />
            <div className="mx-auto flex w-full max-w-7xl min-w-0 flex-1 flex-col gap-5 px-5 pt-4 pb-10">
                <PageHeader
                    title="Users"
                    description="Account status, platform roles, workspace counts, and entitlement source. Content and visitor data are never exposed here."
                    actions={
                        canBroadcast ? (
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setBroadcastOpen(true)}
                            >
                                <Megaphone aria-hidden="true" />
                                Broadcast announcement
                            </Button>
                        ) : undefined
                    }
                />

                <section
                    aria-label="User totals"
                    className="grid grid-cols-2 gap-3 lg:grid-cols-5"
                >
                    {[
                        {
                            label: 'Accounts',
                            value: overview.accounts,
                            Icon: UsersRound,
                        },
                        {
                            label: 'Administrators',
                            value: overview.administrators,
                            Icon: ShieldCheck,
                        },
                        {
                            label: 'Suspended',
                            value: overview.suspended,
                            Icon: Ban,
                        },
                        {
                            label: 'Deletion pending',
                            value: overview.deletionPending,
                            Icon: Clock3,
                        },
                        {
                            label: 'Short links',
                            value: overview.shortLinks,
                            Icon: Link2,
                        },
                    ].map(({ label, value, Icon }, index, stats) => (
                        <StatCard
                            key={label}
                            icon={Icon}
                            label={label}
                            value={value.toLocaleString()}
                            className={
                                index === stats.length - 1
                                    ? 'col-span-2 lg:col-span-1'
                                    : undefined
                            }
                        />
                    ))}
                </section>

                <form
                    className="grid grid-cols-2 items-center gap-2 rounded-2xl border border-border bg-card p-3 shadow-[var(--shadow-surface)] sm:flex sm:flex-wrap"
                    onSubmit={applyFilters}
                >
                    <div className="relative col-span-2 min-w-0 sm:max-w-xs sm:flex-1">
                        <Search
                            className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <Input
                            id="admin-user-search"
                            value={filterState.q}
                            maxLength={100}
                            placeholder="Search name or email"
                            aria-label="Search users"
                            className="h-9 pl-8"
                            onChange={(event) =>
                                setFilterState((current) => ({
                                    ...current,
                                    q: event.target.value,
                                }))
                            }
                        />
                    </div>
                    <FilterSelect
                        id="admin-user-status"
                        label="Status"
                        value={filterState.status}
                        placeholder="Any status"
                        onChange={(value) =>
                            setFilterState((current) => ({
                                ...current,
                                status: value,
                            }))
                        }
                        options={[
                            ['active', 'Active'],
                            ['suspended', 'Suspended'],
                            ['deletion_pending', 'Deletion pending'],
                        ]}
                    />
                    <FilterSelect
                        id="admin-user-verification"
                        label="Verification"
                        value={filterState.verification}
                        placeholder="Any verification"
                        onChange={(value) =>
                            setFilterState((current) => ({
                                ...current,
                                verification: value,
                            }))
                        }
                        options={[
                            ['verified', 'Verified'],
                            ['unverified', 'Unverified'],
                        ]}
                    />
                    <FilterSelect
                        id="admin-user-role"
                        label="Role"
                        value={filterState.role}
                        placeholder="Any role"
                        onChange={(value) =>
                            setFilterState((current) => ({
                                ...current,
                                role: value,
                            }))
                        }
                        options={[
                            ...roles.map(
                                (role) =>
                                    [role.code, role.name] as [string, string],
                            ),
                            ['none', 'No platform role'],
                        ]}
                    />
                    <FilterSelect
                        id="admin-user-plan"
                        label="Plan"
                        value={filterState.plan}
                        placeholder="Any plan"
                        onChange={(value) =>
                            setFilterState((current) => ({
                                ...current,
                                plan: value,
                            }))
                        }
                        options={plans.map(
                            (plan) =>
                                [plan.code, plan.name] as [string, string],
                        )}
                    />
                    <Button
                        type="submit"
                        size="sm"
                        className="h-9 w-full sm:w-auto"
                    >
                        Apply
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        className="h-9 w-full sm:w-auto"
                        disabled={activeFilterCount === 0}
                        onClick={resetFilters}
                    >
                        Reset
                    </Button>
                </form>

                <div className="lp-table-shell hidden lg:block">
                    <table
                        data-table-surface={
                            paginator.data.length > 0 ? 'populated' : undefined
                        }
                        className="w-full border-collapse text-sm"
                        aria-label="Users"
                    >
                        <thead className="bg-surface-secondary text-left text-xs text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3 font-medium">
                                    Account
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Status
                                </th>
                                <th className="px-4 py-3 font-medium">Roles</th>
                                <th className="px-4 py-3 font-medium">
                                    Workspaces
                                </th>
                                <th className="px-4 py-3 font-medium">Plan</th>
                                <th className="px-4 py-3 font-medium">
                                    Last seen
                                </th>
                                <th className="px-4 py-3 text-right font-medium">
                                    <span className="sr-only">Open</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {paginator.data.map((account) => (
                                <tr
                                    key={account.id}
                                    className="h-14 border-t border-separator"
                                >
                                    <td className="px-4 py-2.5">
                                        <AccountIdentity account={account} />
                                    </td>
                                    <td className="px-4 py-2.5">
                                        <div className="flex flex-col items-start gap-1">
                                            <AccountStatusBadge
                                                status={account.status}
                                            />
                                            <VerificationBadge
                                                verified={account.emailVerified}
                                            />
                                        </div>
                                    </td>
                                    <td className="px-4 py-2.5">
                                        <RoleBadges roles={account.roles} />
                                    </td>
                                    <td className="px-4 py-2.5">
                                        <p className="font-medium tabular-nums">
                                            {account.workspacesCount}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {account.personalWorkspace
                                                ? `${account.personalWorkspace.membersCount} ${account.personalWorkspace.membersCount === 1 ? 'member' : 'members'}`
                                                : 'No personal workspace'}
                                        </p>
                                    </td>
                                    <td className="px-4 py-2.5">
                                        <PlanBadge plan={account.plan} />
                                    </td>
                                    <td className="px-4 py-2.5 text-muted-foreground">
                                        {formatAdminDate(account.lastSeenAt)}
                                    </td>
                                    <td className="px-4 py-2.5 text-right">
                                        <Button
                                            asChild
                                            size="icon"
                                            variant="secondary"
                                            className="size-8 rounded-full"
                                            title="Open account"
                                        >
                                            <Link
                                                href={
                                                    users.show(account.id).url
                                                }
                                                aria-label={`Open ${account.name}`}
                                            >
                                                <ExternalLink aria-hidden="true" />
                                            </Link>
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    {paginator.data.length === 0 && (
                        <p className="p-8 text-center text-sm text-muted-foreground">
                            No accounts match these filters.
                        </p>
                    )}
                </div>

                <div className="grid gap-3 lg:hidden">
                    {paginator.data.map((account) => (
                        <AccountCard key={account.id} account={account} />
                    ))}
                    {paginator.data.length === 0 && (
                        <Card variant="transparent">
                            <CardContent className="p-8 text-center text-sm text-muted-foreground">
                                No accounts match these filters.
                            </CardContent>
                        </Card>
                    )}
                </div>

                <footer className="flex flex-col gap-3 text-sm text-muted-foreground sm:flex-row sm:items-center sm:justify-between">
                    <p>
                        Showing {paginator.from ?? 0}–{paginator.to ?? 0} of{' '}
                        {paginator.total.toLocaleString()} accounts
                    </p>
                    {paginator.last_page > 1 && (
                        <nav
                            className="flex flex-wrap gap-1"
                            aria-label="User pagination"
                        >
                            {paginator.links.map((link) =>
                                link.url === null ? (
                                    <span
                                        key={link.label}
                                        className="rounded-md border border-border px-3 py-2 text-muted-foreground/50"
                                    >
                                        {paginationLabel(link.label)}
                                    </span>
                                ) : (
                                    <Link
                                        key={link.label}
                                        href={link.url}
                                        preserveScroll
                                        className={`inline-flex h-9 min-w-9 items-center justify-center rounded-md border px-2.5 ${
                                            link.active
                                                ? 'bg-primary text-primary-foreground'
                                                : 'hover:bg-muted'
                                        }`}
                                    >
                                        {paginationLabel(link.label)}
                                    </Link>
                                ),
                            )}
                        </nav>
                    )}
                </footer>
            </div>

            <Dialog open={broadcastOpen} onOpenChange={setBroadcastOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Broadcast announcement</DialogTitle>
                        <DialogDescription>
                            Send an in-app announcement to every active account.
                            Email follows each recipient’s notification
                            preferences.
                        </DialogDescription>
                    </DialogHeader>
                    <form className="grid gap-3" onSubmit={submitBroadcast}>
                        <div className="grid gap-2">
                            <Label htmlFor="broadcast-title">Title</Label>
                            <Input
                                id="broadcast-title"
                                value={broadcastForm.data.title}
                                maxLength={100}
                                required
                                onChange={(event) =>
                                    broadcastForm.setData(
                                        'title',
                                        event.target.value,
                                    )
                                }
                            />
                            {broadcastForm.errors.title && (
                                <p
                                    role="alert"
                                    className="text-sm text-destructive"
                                >
                                    {broadcastForm.errors.title}
                                </p>
                            )}
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="broadcast-body">Message</Label>
                            <Textarea
                                id="broadcast-body"
                                value={broadcastForm.data.body}
                                maxLength={2000}
                                required
                                rows={4}
                                onChange={(event) =>
                                    broadcastForm.setData(
                                        'body',
                                        event.target.value,
                                    )
                                }
                            />
                            {broadcastForm.errors.body && (
                                <p
                                    role="alert"
                                    className="text-sm text-destructive"
                                >
                                    {broadcastForm.errors.body}
                                </p>
                            )}
                        </div>
                        <DialogFooter className="gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                disabled={broadcastForm.processing}
                                onClick={() => setBroadcastOpen(false)}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                disabled={broadcastForm.processing}
                            >
                                {broadcastForm.processing
                                    ? 'Sending…'
                                    : 'Send announcement'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}

function FilterSelect({
    id,
    label,
    value,
    placeholder,
    options,
    onChange,
}: {
    id: string;
    label: string;
    value: string | null;
    placeholder: string;
    options: Array<[string, string]>;
    onChange: (value: string | null) => void;
}) {
    return (
        <Select
            value={value ?? 'any'}
            onValueChange={(next) => onChange(next === 'any' ? null : next)}
        >
            <SelectTrigger
                id={id}
                aria-label={label}
                className="h-9 w-full min-w-36 sm:w-auto"
            >
                <SelectValue placeholder={placeholder} />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value="any">{placeholder}</SelectItem>
                {options.map(([option, optionLabel]) => (
                    <SelectItem key={option} value={option}>
                        {optionLabel}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}

function AccountIdentity({ account }: { account: AdminUserListItem }) {
    return (
        <div className="flex min-w-0 items-center gap-3">
            <AccountAvatar account={account} />
            <div className="min-w-0">
                <Link
                    href={users.show(account.id).url}
                    className="block truncate font-medium hover:underline"
                >
                    {account.name}
                </Link>
                <p className="truncate text-xs text-muted-foreground">
                    {account.email}
                </p>
            </div>
        </div>
    );
}

function AccountAvatar({ account }: { account: AdminUserListItem }) {
    return (
        <UserAvatar
            name={account.name}
            src={account.avatarUrl}
            seed={account.id}
            className="size-9 shrink-0"
        />
    );
}

function AccountCard({ account }: { account: AdminUserListItem }) {
    return (
        <Card>
            <CardHeader className="gap-2">
                <div className="flex items-start justify-between gap-3">
                    <AccountIdentity account={account} />
                    <AccountStatusBadge status={account.status} />
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    <VerificationBadge verified={account.emailVerified} />
                    <RoleBadges roles={account.roles} />
                </div>
            </CardHeader>
            <CardContent className="grid gap-3 text-sm">
                <dl className="grid grid-cols-2 gap-2">
                    <div>
                        <dt className="text-xs text-muted-foreground">Plan</dt>
                        <dd>
                            <PlanBadge plan={account.plan} />
                        </dd>
                    </div>
                    <div>
                        <dt className="text-xs text-muted-foreground">
                            Workspaces
                        </dt>
                        <dd className="tabular-nums">
                            {account.workspacesCount}
                            {account.personalWorkspace
                                ? ` · ${account.personalWorkspace.membersCount} ${account.personalWorkspace.membersCount === 1 ? 'member' : 'members'}`
                                : ''}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-xs text-muted-foreground">
                            Last seen
                        </dt>
                        <dd>{formatAdminDate(account.lastSeenAt)}</dd>
                    </div>
                    <div>
                        <dt className="text-xs text-muted-foreground">
                            Created
                        </dt>
                        <dd>{formatAdminDate(account.createdAt)}</dd>
                    </div>
                </dl>
                <Button asChild variant="outline" className="min-h-11 w-full">
                    <Link href={users.show(account.id).url}>Open account</Link>
                </Button>
            </CardContent>
        </Card>
    );
}
