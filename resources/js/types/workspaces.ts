export type WorkspaceRole = 'owner' | 'admin' | 'member';

export type WorkspaceMembership = {
    id: number;
    name: string;
    slug: string;
    role: WorkspaceRole;
};

export type WorkspaceUsageMetric = {
    label: string;
    used: number;
    limit: number | null;
    overLimit: boolean;
};

export type WorkspaceContext = {
    current: WorkspaceMembership & {
        plan: string;
        usage: Record<string, WorkspaceUsageMetric>;
    };
    memberships: WorkspaceMembership[];
};

export type WorkspaceMember = {
    id: number;
    name: string;
    email: string;
    role: WorkspaceRole;
    joined_at: string;
};

export type WorkspaceInvitation = {
    id: number;
    email: string;
    role: 'admin' | 'member';
    expires_at: string;
    sent_at: string | null;
    status: 'pending' | 'expired' | 'revoked';
};

export type WorkspaceSettingsProps = {
    workspaces: WorkspaceMembership[];
    members: WorkspaceMember[];
    invitations?: WorkspaceInvitation[];
    canManageMembers: boolean;
    canTransferOwnership: boolean;
    memberCapacity: {
        plan: string;
        limit: number | null;
        members: number;
        pending: number;
        available: number | null;
    };
    flash?: { success?: string; invitationUrl?: string };
};

export type InvitationAcceptance = {
    token: string;
    invitation: {
        workspace_name: string;
        email: string;
        role: 'admin' | 'member';
        expires_at: string;
    };
};
