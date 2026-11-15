import { Badge } from '@/components/ui/badge';
import type { AdminRoleSummary, AdminUserStatus } from '@/types/admin';

export function AccountStatusBadge({ status }: { status: AdminUserStatus }) {
    const meta =
        status === 'suspended'
            ? {
                  label: 'Suspended',
                  variant: 'destructive' as const,
                  dot: 'bg-danger',
              }
            : status === 'deletion_pending'
              ? {
                    label: 'Deletion pending',
                    variant: 'warning' as const,
                    dot: 'bg-warning',
                }
              : {
                    label: 'Active',
                    variant: 'success' as const,
                    dot: 'bg-success',
                };

    return (
        <Badge variant={meta.variant} className="gap-1.5 py-0">
            <span
                aria-hidden="true"
                className={`size-1.5 rounded-full ${meta.dot}`}
            />
            {meta.label}
        </Badge>
    );
}

export function VerificationBadge({ verified }: { verified: boolean }) {
    return (
        <span className="text-xs text-muted-foreground">
            {verified ? 'Verified' : 'Unverified'}
        </span>
    );
}

export function RoleBadges({ roles }: { roles: AdminRoleSummary[] }) {
    if (roles.length === 0) {
        return <span className="text-sm text-muted-foreground">No role</span>;
    }

    return (
        <div className="flex flex-wrap gap-1">
            {roles.map((role) => (
                <Badge
                    key={role.code}
                    variant="outline"
                    className="text-[11px]"
                >
                    {role.name}
                </Badge>
            ))}
        </div>
    );
}

export function PlanBadge({
    plan,
}: {
    plan: {
        name: string;
        source: string;
        subscriptionStatus: string | null;
    } | null;
}) {
    if (plan === null) {
        return <span className="text-sm text-muted-foreground">No plan</span>;
    }

    return (
        <div className="flex flex-wrap items-baseline gap-1.5">
            <span className="text-sm font-medium">{plan.name}</span>
            <span className="text-xs text-muted-foreground">
                {plan.source === 'free'
                    ? 'free'
                    : plan.source === 'stripe'
                      ? (plan.subscriptionStatus ?? 'stripe')
                      : 'override'}
            </span>
        </div>
    );
}
