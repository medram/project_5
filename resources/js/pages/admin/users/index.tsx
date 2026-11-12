import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { type FormEvent, useEffect, useMemo, useState } from 'react';
import { Megaphone, Search, SlidersHorizontal, Users } from 'lucide-react';
import {
    AccountStatusBadge,
    PlanBadge,
    RoleBadges,
    VerificationBadge,
} from '@/components/admin/admin-badges';
import { formatAdminDate } from '@/components/admin/format';
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
        .replace('&laquo;', '‹')
        .replace('&raquo;', '›')
        .replace('Previous', 'Previous')
        .replace('Next', 'Next');
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
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <header className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div className="space-y-1">
                        <div className="flex items-center gap-2 text-sm text-muted-foreground">
                            <Users className="size-4" aria-hidden="true" />
                            <span>Platform administration</span>
                        </div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Users
                        </h1>
                        <p className="max-w-2xl text-sm text-muted-foreground">
                            Review account metadata, platform roles, workspace
                            counts, and entitlement sources. Private content and
                            visitor data are never exposed here.
                        </p>
                    </div>
                    {canBroadcast && (
                        <Button
                            type="button"
                            className="min-h-11 self-start"
                            onClick={() => setBroadcastOpen(true)}
                        >
                            <Megaphone aria-hidden="true" />
                            Broadcast announcement
                        </Button>
                    )}
                </header>

                <section className="grid grid-cols-2 gap-3 xl:grid-cols-5">
                    {[
                        ['Accounts', overview.accounts],
                        ['Administrators', overview.administrators],
                        ['Suspended', overview.suspended],
                        ['Deletion pending', overview.deletionPending],
                        ['Short links', overview.shortLinks],
                    ].map(([label, value]) => (
                        <Card key={String(label)}>
                            <CardHeader className="gap-1 pb-2">
                                <CardDescription>{label}</CardDescription>
                                <CardTitle className="text-2xl tabular-nums">
                                    {Number(value).toLocaleString()}
                                </CardTitle>
                            </CardHeader>
                        </Card>
                    ))}
                </section>

                <Card>
                    <CardHeader className="gap-1">
                        <CardTitle className="flex items-center gap-2">
                            <SlidersHorizontal
                                className="size-4"
                                aria-hidden="true"
                            />
                            Filters
                        </CardTitle>
                        <CardDescription>
                            Filters are stored in the URL so links and mutation
                            redirects preserve the current view.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form
                            className="grid gap-3 sm:grid-cols-2 xl:grid-cols-[minmax(0,2fr)_repeat(4,minmax(0,1fr))_auto]"
                            onSubmit={applyFilters}
                        >
                            <div className="grid gap-2">
                                <Label htmlFor="admin-user-search">
                                    Search
                                </Label>
                                <div className="relative">
                                    <Search
                                        className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                        aria-hidden="true"
                                    />
                                    <Input
                                        id="admin-user-search"
                                        value={filterState.q}
                                        maxLength={100}
                                        placeholder="Name or email"
                                        className="pl-9"
                                        onChange={(event) =>
                                            setFilterState((current) => ({
                                                ...current,
                                                q: event.target.value,
                                            }))
                                        }
                                    />
                                </div>
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
                                            [role.code, role.name] as [
                                                string,
                                                string,
                                            ],
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
                                        [plan.code, plan.name] as [
                                            string,
                                            string,
                                        ],
                                )}
                            />
                            <div className="flex items-end gap-2">
                                <Button
                                    type="submit"
                                    className="min-h-11 flex-1"
                                >
                                    Apply
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    className="min-h-11"
                                    disabled={activeFilterCount === 0}
                                    onClick={resetFilters}
                                >
                                    Reset
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <div className="hidden overflow-hidden rounded-lg border lg:block">
                    <table className="w-full border-collapse text-sm">
                        <thead className="bg-muted/50 text-left text-xs text-muted-foreground uppercase">
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
                                    className="border-t align-top"
                                >
                                    <td className="px-4 py-3">
                                        <AccountIdentity account={account} />
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex flex-col items-start gap-1">
                                            <AccountStatusBadge
                                                status={account.status}
                                            />
                                            <VerificationBadge
                                                verified={account.emailVerified}
                                            />
                                        </div>
                                    </td>
                                    <td className="px-4 py-3">
                                        <RoleBadges roles={account.roles} />
                                    </td>
                                    <td className="px-4 py-3">
                                        <p className="font-medium tabular-nums">
                                            {account.workspacesCount}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {account.personalWorkspace
                                                ? `${account.personalWorkspace.membersCount} members`
                                                : 'No personal workspace'}
                                        </p>
                                    </td>
                                    <td className="px-4 py-3">
                                        <PlanBadge plan={account.plan} />
                                    </td>
                                    <td className="px-4 py-3 text-muted-foreground">
                                        {formatAdminDate(account.lastSeenAt)}
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <Button
                                            asChild
                                            size="sm"
                                            variant="outline"
                                            className="min-h-9"
                                        >
                                            <Link
                                                href={
                                                    users.show(account.id).url
                                                }
                                            >
                                                Open
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
                        <Card>
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
                                        className="rounded-md border px-3 py-2 opacity-50"
                                    >
                                        {paginationLabel(link.label)}
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
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            <Select
                value={value ?? 'any'}
                onValueChange={(next) => onChange(next === 'any' ? null : next)}
            >
                <SelectTrigger id={id} className="min-h-9 w-full">
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
        </div>
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
    if (account.avatarUrl) {
        return (
            <img
                src={account.avatarUrl}
                alt=""
                className="size-9 shrink-0 rounded-full border object-cover"
            />
        );
    }

    return (
        <span
            className="flex size-9 shrink-0 items-center justify-center rounded-full border bg-muted text-xs font-medium uppercase"
            aria-hidden="true"
        >
            {account.name.slice(0, 2)}
        </span>
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
                                ? ` · ${account.personalWorkspace.membersCount} members`
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
