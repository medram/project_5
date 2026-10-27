import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';
import { Link2, Megaphone, ShieldCheck, UserRoundX, Users } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { broadcast as broadcastNotification } from '@/routes/admin/notifications';
import { index as adminIndex } from '@/routes/admin';
import type { AdminAccount, AdminPageProps } from '@/types/admin';

type PendingAdminAction =
    | { type: 'suspension'; account: AdminAccount; suspended: boolean }
    | { type: 'administrator'; account: AdminAccount; isAdmin: boolean }
    | {
          type: 'plan';
          account: AdminAccount;
          planCode: string;
          planName: string;
      };

function actionDetails(action: PendingAdminAction) {
    if (action.type === 'suspension') {
        return action.suspended
            ? {
                  title: `Suspend ${action.account.name}?`,
                  description: `This blocks ${action.account.email} from signing in until an administrator restores the account.`,
                  label: 'Suspend account',
                  destructive: true,
              }
            : {
                  title: `Restore ${action.account.name}?`,
                  description: `This restores sign-in access for ${action.account.email}.`,
                  label: 'Restore account',
                  destructive: false,
              };
    }

    if (action.type === 'administrator') {
        return action.isAdmin
            ? {
                  title: `Grant administrator access to ${action.account.name}?`,
                  description: `This gives ${action.account.email} platform-administrator privileges.`,
                  label: 'Grant administrator',
                  destructive: false,
              }
            : {
                  title: `Revoke administrator access from ${action.account.name}?`,
                  description: `This removes platform-administrator privileges from ${action.account.email}.`,
                  label: 'Revoke administrator',
                  destructive: true,
              };
    }

    return {
        title: `Change the plan for ${action.account.name}?`,
        description: `This changes the personal workspace plan from ${action.account.subscription.planName ?? 'No plan'} to ${action.planName}.`,
        label: `Assign ${action.planName}`,
        destructive: false,
    };
}

function accountStatus(account: AdminAccount): string {
    return account.isSuspended ? 'Suspended' : 'Active';
}

export default function AdminIndex({
    overview,
    plans,
    search,
    users,
}: AdminPageProps) {
    const { auth } = usePage().props;

    const [pendingAction, setPendingAction] =
        useState<PendingAdminAction | null>(null);
    const [isProcessing, setIsProcessing] = useState(false);
    const announcementForm = useForm({ title: '', body: '' });

    function submitAnnouncement(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        announcementForm.post(broadcastNotification().url, {
            preserveScroll: true,
            onSuccess: () => announcementForm.reset(),
        });
    }

    function confirmAction() {
        if (!pendingAction) {
            return;
        }

        setIsProcessing(true);
        const query = search ? `?${new URLSearchParams({ q: search })}` : '';
        const options = {
            preserveScroll: true,
            onSuccess: () => setPendingAction(null),
            onFinish: () => setIsProcessing(false),
        };

        switch (pendingAction.type) {
            case 'suspension':
                router.patch(
                    `/admin/users/${pendingAction.account.id}/suspension${query}`,
                    { suspended: pendingAction.suspended },
                    options,
                );
                break;
            case 'administrator':
                router.patch(
                    `/admin/users/${pendingAction.account.id}/administrator${query}`,
                    { is_admin: pendingAction.isAdmin },
                    options,
                );
                break;
            case 'plan':
                router.patch(
                    `/admin/users/${pendingAction.account.id}/plan${query}`,
                    { plan: pendingAction.planCode },
                    options,
                );
                break;
        }
    }

    function requestPlanChange(account: AdminAccount, planCode: string) {
        const plan = plans.find((candidate) => candidate.code === planCode);

        if (!plan || plan.code === account.subscription.planCode) {
            return;
        }

        setPendingAction({
            type: 'plan',
            account,
            planCode: plan.code,
            planName: plan.name,
        });
    }

    const confirmation = pendingAction ? actionDetails(pendingAction) : null;

    return (
        <>
            <Head title="Administration" />
            <Dialog
                open={pendingAction !== null}
                onOpenChange={(open) => {
                    if (!open && !isProcessing) {
                        setPendingAction(null);
                    }
                }}
            >
                <DialogContent>
                    {confirmation && (
                        <>
                            <DialogHeader>
                                <DialogTitle>{confirmation.title}</DialogTitle>
                                <DialogDescription>
                                    {confirmation.description}
                                </DialogDescription>
                            </DialogHeader>
                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        disabled={isProcessing}
                                    >
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button
                                    type="button"
                                    variant={
                                        confirmation.destructive
                                            ? 'destructive'
                                            : 'default'
                                    }
                                    disabled={isProcessing}
                                    onClick={confirmAction}
                                >
                                    {isProcessing
                                        ? 'Saving…'
                                        : confirmation.label}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </DialogContent>
            </Dialog>

            <div className="flex flex-col gap-6 p-4 sm:p-6">
                <header className="flex flex-col gap-2">
                    <div className="flex items-center gap-2 text-muted-foreground">
                        <ShieldCheck className="size-4" aria-hidden="true" />
                        <span className="text-sm font-medium">
                            Platform administration
                        </span>
                    </div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Account controls
                    </h1>
                    <p className="max-w-2xl text-sm text-muted-foreground">
                        Manage account status, administrator access, and
                        personal-workspace entitlements. Private content and
                        visitor data are not exposed here.
                    </p>
                </header>

                <Card id="announcements" className="scroll-mt-20">
                    <CardHeader>
                        <CardTitle>Platform announcement</CardTitle>
                        <CardDescription>
                            Send an in-app announcement to all active accounts.
                            Email follows each recipient’s category preferences
                            and the configured mail service.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form
                            onSubmit={submitAnnouncement}
                            className="grid gap-4 md:grid-cols-2"
                        >
                            <div className="grid gap-2">
                                <label
                                    htmlFor="announcement-title"
                                    className="text-sm font-medium"
                                >
                                    Title
                                </label>
                                <Input
                                    id="announcement-title"
                                    value={announcementForm.data.title}
                                    maxLength={100}
                                    required
                                    onChange={(event) =>
                                        announcementForm.setData(
                                            'title',
                                            event.target.value,
                                        )
                                    }
                                    aria-invalid={Boolean(
                                        announcementForm.errors.title,
                                    )}
                                />
                                {announcementForm.errors.title && (
                                    <p
                                        role="alert"
                                        className="text-sm text-destructive"
                                    >
                                        {announcementForm.errors.title}
                                    </p>
                                )}
                            </div>
                            <div className="grid gap-2">
                                <label
                                    htmlFor="announcement-body"
                                    className="text-sm font-medium"
                                >
                                    Message
                                </label>
                                <Textarea
                                    id="announcement-body"
                                    value={announcementForm.data.body}
                                    maxLength={2000}
                                    required
                                    rows={3}
                                    onChange={(event) =>
                                        announcementForm.setData(
                                            'body',
                                            event.target.value,
                                        )
                                    }
                                    aria-invalid={Boolean(
                                        announcementForm.errors.body,
                                    )}
                                />
                                {announcementForm.errors.body && (
                                    <p
                                        role="alert"
                                        className="text-sm text-destructive"
                                    >
                                        {announcementForm.errors.body}
                                    </p>
                                )}
                            </div>
                            <div className="md:col-span-2">
                                <Button
                                    type="submit"
                                    disabled={
                                        announcementForm.processing ||
                                        !announcementForm.data.title.trim() ||
                                        !announcementForm.data.body.trim()
                                    }
                                >
                                    <Megaphone aria-hidden="true" />
                                    {announcementForm.processing
                                        ? 'Sending…'
                                        : 'Send announcement'}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <div
                    id="overview"
                    className="grid scroll-mt-20 gap-4 sm:grid-cols-2 xl:grid-cols-4"
                >
                    <MetricCard
                        icon={Users}
                        label="Accounts"
                        value={overview.accounts}
                    />
                    <MetricCard
                        icon={ShieldCheck}
                        label="Administrators"
                        value={overview.administrators}
                    />
                    <MetricCard
                        icon={UserRoundX}
                        label="Suspended"
                        value={overview.suspendedAccounts}
                    />
                    <div id="content" className="scroll-mt-20">
                        <MetricCard
                            icon={Link2}
                            label="Short links"
                            value={overview.shortLinks}
                        />
                    </div>
                </div>

                <Card id="accounts" className="scroll-mt-20">
                    <CardHeader>
                        <CardTitle>Accounts</CardTitle>
                        <CardDescription>
                            Metadata and aggregate ownership counts only.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <form
                            method="get"
                            action={adminIndex().url}
                            role="search"
                            className="flex flex-col gap-2 sm:flex-row"
                        >
                            <label
                                htmlFor="admin-account-search"
                                className="sr-only"
                            >
                                Search accounts and plans
                            </label>
                            <Input
                                id="admin-account-search"
                                className="min-h-11"
                                type="search"
                                name="q"
                                maxLength={100}
                                defaultValue={search}
                                placeholder="Search names, emails, or plans…"
                            />
                            <Button
                                type="submit"
                                variant="outline"
                                className="min-h-11"
                            >
                                Search
                            </Button>
                            {search && (
                                <Button
                                    asChild
                                    variant="ghost"
                                    className="min-h-11"
                                >
                                    <Link href={adminIndex()}>
                                        Clear search
                                    </Link>
                                </Button>
                            )}
                        </form>
                        <div
                            id="plan-assignments"
                            className="scroll-mt-20 space-y-1"
                        >
                            <h2 className="font-medium">
                                Personal workspace plan assignments
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                Change the plan assigned to another account's
                                personal workspace.
                            </p>
                        </div>
                        {users.length === 0 && (
                            <p
                                role="status"
                                className="text-sm text-muted-foreground"
                            >
                                {search
                                    ? `No accounts match “${search}”.`
                                    : 'No accounts yet.'}
                            </p>
                        )}
                        {users.map((account) => {
                            const isCurrentAccount =
                                account.id === auth.user?.id;

                            return (
                                <section
                                    key={account.id}
                                    className="grid gap-4 rounded-lg border p-4 lg:grid-cols-[minmax(0,1fr)_auto_auto] lg:items-center"
                                >
                                    <div className="min-w-0 space-y-2">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <p className="font-medium">
                                                {account.name}
                                            </p>
                                            <Badge
                                                variant={
                                                    account.isSuspended
                                                        ? 'destructive'
                                                        : 'secondary'
                                                }
                                            >
                                                {accountStatus(account)}
                                            </Badge>
                                            {account.isAdmin && (
                                                <Badge variant="outline">
                                                    Administrator
                                                </Badge>
                                            )}
                                            {isCurrentAccount && (
                                                <Badge variant="outline">
                                                    Your account
                                                </Badge>
                                            )}
                                        </div>
                                        <p className="truncate text-sm text-muted-foreground">
                                            {account.email}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {account.personalWorkspaceName ??
                                                'No personal workspace'}
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            {account.subscription.planName ??
                                                'No plan'}{' '}
                                            ·{' '}
                                            {account.subscription.status ??
                                                'missing subscription'}{' '}
                                            · {account.counts.links} short links
                                            · {account.counts.bioPages} bio
                                            pages
                                        </p>
                                    </div>

                                    <Select
                                        value={
                                            account.subscription.planCode ?? ''
                                        }
                                        onValueChange={(planCode) =>
                                            requestPlanChange(account, planCode)
                                        }
                                        disabled={isCurrentAccount}
                                    >
                                        <SelectTrigger
                                            className="min-h-11 w-full lg:w-40"
                                            aria-label={`Personal workspace plan for ${account.email}`}
                                        >
                                            <SelectValue placeholder="Select plan" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {plans.map((plan) => (
                                                <SelectItem
                                                    className="min-h-11"
                                                    key={plan.code}
                                                    value={plan.code}
                                                >
                                                    {plan.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>

                                    {isCurrentAccount ? (
                                        <span className="text-sm text-muted-foreground">
                                            Your safeguards are managed by a
                                            different administrator.
                                        </span>
                                    ) : (
                                        <div className="flex flex-wrap gap-2">
                                            <Button
                                                type="button"
                                                size="sm"
                                                className="min-h-11"
                                                variant={
                                                    account.isSuspended
                                                        ? 'outline'
                                                        : 'destructive'
                                                }
                                                onClick={() =>
                                                    setPendingAction({
                                                        type: 'suspension',
                                                        account,
                                                        suspended:
                                                            !account.isSuspended,
                                                    })
                                                }
                                            >
                                                {account.isSuspended
                                                    ? 'Restore account'
                                                    : 'Suspend account'}
                                            </Button>
                                            <Button
                                                type="button"
                                                className="min-h-11"
                                                size="sm"
                                                variant="outline"
                                                onClick={() =>
                                                    setPendingAction({
                                                        type: 'administrator',
                                                        account,
                                                        isAdmin:
                                                            !account.isAdmin,
                                                    })
                                                }
                                            >
                                                {account.isAdmin
                                                    ? 'Revoke administrator'
                                                    : 'Grant administrator'}
                                            </Button>
                                        </div>
                                    )}
                                </section>
                            );
                        })}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

function MetricCard({
    icon: Icon,
    label,
    value,
}: {
    icon: typeof Users;
    label: string;
    value: number;
}) {
    return (
        <Card>
            <CardContent className="flex items-center gap-3 p-4">
                <Icon
                    className="size-4 text-muted-foreground"
                    aria-hidden="true"
                />
                <div>
                    <p className="text-sm text-muted-foreground">{label}</p>
                    <p className="text-2xl font-semibold">
                        {value.toLocaleString()}
                    </p>
                </div>
            </CardContent>
        </Card>
    );
}
