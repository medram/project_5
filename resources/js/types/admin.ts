export type AdminPlan = {
    code: string;
    name: string;
};

export type AdminAccount = {
    id: number;
    name: string;
    email: string;
    emailVerifiedAt: string | null;
    createdAt: string | null;
    isAdmin: boolean;
    isSuspended: boolean;
    suspendedAt: string | null;
    personalWorkspaceName: string | null;
    subscription: {
        status: string | null;
        planCode: string | null;
        planName: string | null;
    };
    counts: {
        links: number;
        bioPages: number;
    };
};

export type AdminPageProps = {
    overview: {
        accounts: number;
        administrators: number;
        suspendedAccounts: number;
        shortLinks: number;
    };
    plans: AdminPlan[];
    search: string;
    users: AdminAccount[];
};
