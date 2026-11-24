export type AdminPlan = {
    code: string;
    name: string;
};

export type AdminRoleSummary = {
    code: string;
    name: string;
};

export type AdminPermission = {
    value: string;
    label: string;
};

export type AdminRoleDefinition = AdminRoleSummary & {
    permissions: AdminPermission[];
    usersCount?: number;
    isBuiltIn: boolean;
    isAssignedToActor: boolean;
    members?: Array<{
        id: number;
        name: string;
        email: string;
    }>;
};

export type AdminPlanSummary = {
    code: string;
    name: string;
    source: string;
    subscriptionStatus: string | null;
};

export type AdminUserStatus = 'active' | 'suspended' | 'deletion_pending';

export type AdminUserListItem = {
    id: number;
    name: string;
    email: string;
    avatarUrl: string | null;
    status: AdminUserStatus;
    emailVerified: boolean;
    roles: AdminRoleSummary[];
    workspacesCount: number;
    personalWorkspace: {
        name: string;
        membersCount: number;
        linksCount: number;
        bioPagesCount: number;
        domainsCount: number;
    } | null;
    plan: AdminPlanSummary | null;
    lastSeenAt: string | null;
    createdAt: string | null;
};

export type AdminUserFilters = {
    q: string;
    status: string | null;
    verification: string | null;
    role: string | null;
    plan: string | null;
};

export type AdminPaginator<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: Array<{
        url: string | null;
        label: string;
        active: boolean;
    }>;
};

export type AdminUsersPageProps = {
    users: AdminPaginator<AdminUserListItem>;
    filters: AdminUserFilters;
    plans: AdminPlan[];
    roles: AdminRoleSummary[];
    overview: {
        accounts: number;
        administrators: number;
        suspended: number;
        deletionPending: number;
        shortLinks: number;
    };
};

export type AdminWorkspaceSummary = {
    id: number;
    name: string;
    role: string;
    isOwner: boolean;
    isPersonal: boolean;
    membersCount: number;
    linksCount: number;
    bioPagesCount: number;
    domainsCount: number;
    plan: AdminPlanSummary;
};

export type AdminUserDetail = {
    id: number;
    name: string;
    email: string;
    avatarUrl: string | null;
    status: AdminUserStatus;
    emailVerified: boolean;
    emailVerifiedAt: string | null;
    roles: AdminRoleSummary[];
    createdAt: string | null;
    updatedAt: string | null;
    lastSeenAt: string | null;
    suspendedAt: string | null;
    deletionRequestedAt: string | null;
    purgeAfter: string | null;
    isSelf: boolean;
};

export type AdminTokenSummary = {
    id: number;
    name: string;
    abilities: string[];
    lastUsedAt: string | null;
    expiresAt: string | null;
    createdAt: string | null;
};

export type AdminUserDetailProps = {
    account: AdminUserDetail;
    security: {
        twoFactorEnabled: boolean;
        passkeyCount: number;
        sessionCount: number;
        latestSessionAt: string | null;
        tokenCount: number;
        tokens: AdminTokenSummary[];
    };
    workspaces: AdminWorkspaceSummary[];
    roles: AdminRoleDefinition[];
    plans: AdminPlan[];
};

export type AdminRolesPageProps = {
    roles: AdminRoleDefinition[];
    allPermissions: AdminPermission[];
};
