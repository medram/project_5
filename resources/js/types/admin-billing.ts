export type AdminPlanRecord = {
    code: string;
    name: string;
    currency: string;
    monthlyPrice: number;
    annualPrice: number;
    description: string | null;
    featured: boolean;
    sortOrder: number;
    isActive: boolean;
    stripeMonthlyPriceId: string | null;
    stripeYearlyPriceId: string | null;
    prices: Array<{
        id: string;
        interval: 'month' | 'year';
        currency: string;
        amount: number | null;
        verified: boolean;
    }>;
    checkout: {
        month: boolean;
        year: boolean;
    };
    limits: Record<string, number | null>;
};

export type AdminBillingReadiness = {
    configured?: boolean;
    checkoutReady?: boolean;
    missing: string[];
};

export type AdminPlansPageProps = {
    plans: AdminPlanRecord[];
    subscriptionCurrency: string;
    readiness: AdminBillingReadiness;
};

export type AdminOverride = {
    id: number;
    plan: {
        code: string;
        name: string;
    } | null;
    source: string;
    reason: string;
    startsAt: string | null;
    expiresAt: string | null;
};

export type AdminSubscriptionRow = {
    workspace: {
        id: number;
        name: string;
        membersCount: number;
        owner: {
            id: number;
            name: string;
            email: string;
        } | null;
    };
    effectivePlan: {
        code: string;
        name: string;
        source: string;
        overrideExpiresAt: string | null;
    };
    provider: {
        configured: boolean;
        status: string | null;
        priceId: string | null;
        currentPeriodEnd: string | null;
        endsAt: string | null;
        canCancel: boolean;
        canResume: boolean;
    };
    overrides: AdminOverride[];
};

export type AdminSubscriptionsPageProps = {
    workspaces: {
        data: AdminSubscriptionRow[];
        current_page: number;
        last_page: number;
        total: number;
        from: number | null;
        to: number | null;
        links: Array<{
            url: string | null;
            label: string;
            active: boolean;
        }>;
    };
    filters: {
        q: string;
    };
    plans: Array<{
        code: string;
        name: string;
    }>;
    sources: string[];
    readiness: AdminBillingReadiness;
};
