import { Badge } from '@/components/ui/badge';
import type { AdminRoleSummary, AdminUserStatus } from '@/types/admin';

export function AccountStatusBadge({ status }: { status: AdminUserStatus }) {
    if (status === 'suspended') {
        return <Badge variant="destructive">Suspended</Badge>;
    }

    if (status === 'deletion_pending') {
        return <Badge variant="outline">Deletion pending</Badge>;
    }

    return <Badge variant="secondary">Active</Badge>;
}

export function VerificationBadge({ verified }: { verified: boolean }) {
    return verified ? (
        <Badge variant="outline">Verified</Badge>
    ) : (
        <Badge variant="outline" className="text-muted-foreground">
            Unverified
        </Badge>
    );
}

export function RoleBadges({ roles }: { roles: AdminRoleSummary[] }) {
    if (roles.length === 0) {
        return <span className="text-sm text-muted-foreground">No role</span>;
    }

    return (
        <div className="flex flex-wrap gap-1">
            {roles.map((role) => (
                <Badge key={role.code} variant="outline">
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
        <div className="flex flex-wrap items-center gap-1">
            <Badge variant="secondary">{plan.name}</Badge>
            <span className="text-xs text-muted-foreground">
                {plan.source === 'free'
                    ? 'Free'
                    : plan.source === 'stripe'
                      ? (plan.subscriptionStatus ?? 'Stripe')
                      : 'Manual override'}
            </span>
        </div>
    );
}
