import { Head, router, usePage } from '@inertiajs/react';
import { Link2, ShieldCheck, UserRoundX, Users } from 'lucide-react';
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
import type { AdminAccount, AdminPageProps } from '@/types/admin';

function accountStatus(account: AdminAccount): string {
    return account.isSuspended ? 'Suspended' : 'Active';
}

export default function AdminIndex({
    overview,
    plans,
    users,
}: AdminPageProps) {
    const { auth } = usePage().props;

    function setSuspension(account: AdminAccount, suspended: boolean) {
        router.patch(
            `/admin/users/${account.id}/suspension`,
            { suspended },
            { preserveScroll: true },
        );
    }

    function setAdministrator(account: AdminAccount, isAdmin: boolean) {
        router.patch(
            `/admin/users/${account.id}/administrator`,
            { is_admin: isAdmin },
            { preserveScroll: true },
        );
    }

    function setPlan(account: AdminAccount, plan: string) {
        router.patch(
            `/admin/users/${account.id}/plan`,
            { plan },
            { preserveScroll: true },
        );
    }

    return (
        <>
            <Head title="Administration" />

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
                        Manage account status, administrator access, and local
                        entitlements. Private content and visitor data are not
                        exposed here.
                    </p>
                </header>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
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
                    <MetricCard
                        icon={Link2}
                        label="Short links"
                        value={overview.shortLinks}
                    />
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Accounts</CardTitle>
                        <CardDescription>
                            Metadata and aggregate ownership counts only.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {users.map((account) => {
                            const isCurrentAccount = account.id === auth.user?.id;

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
                                        <p className="text-sm text-muted-foreground">
                                            {account.subscription.planName ??
                                                'No plan'}{' '}
                                            ·{' '}
                                            {account.subscription.status ??
                                                'missing subscription'}{' '}
                                            · {account.counts.links} short links
                                            · {account.counts.bioPages} bio pages
                                        </p>
                                    </div>

                                    <Select
                                        value={
                                            account.subscription.planCode ?? ''
                                        }
                                        onValueChange={(plan) =>
                                            setPlan(account, plan)
                                        }
                                        disabled={isCurrentAccount}
                                    >
                                        <SelectTrigger
                                            className="w-full lg:w-40"
                                            aria-label={`Plan for ${account.email}`}
                                        >
                                            <SelectValue placeholder="Select plan" />
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
                                                variant={
                                                    account.isSuspended
                                                        ? 'outline'
                                                        : 'destructive'
                                                }
                                                onClick={() =>
                                                    setSuspension(
                                                        account,
                                                        !account.isSuspended,
                                                    )
                                                }
                                            >
                                                {account.isSuspended
                                                    ? 'Restore account'
                                                    : 'Suspend account'}
                                            </Button>
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                onClick={() =>
                                                    setAdministrator(
                                                        account,
                                                        !account.isAdmin,
                                                    )
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
                <Icon className="size-4 text-muted-foreground" aria-hidden="true" />
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
