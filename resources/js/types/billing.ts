export type PlanLimits = {
    links: number | null;
    folders: number | null;
    qr_campaigns: number | null;
    bio_pages: number | null;
    domains: number | null;
    pixels: number | null;
    members: number | null;
    webhooks: number | null;
    link_pages: number | null;
    api_keys: number | null;
    storage_bytes: number | null;
};

export type BillingPlan = {
    code: string;
    name: string;
    description: string | null;
    monthlyPrice: number | null;
    annualPrice: number | null;
    currency: string;
    featured: boolean;
    sortOrder: number;
    limits: PlanLimits;
    checkout: {
        month: boolean;
        year: boolean;
    };
};

export type UsageMetric = {
    label: string;
    used: number;
    limit: number | null;
    overLimit: boolean;
};

export type ProviderSubscription = {
    status: string;
    price: string | null;
    interval: string | null;
    baseAmount: number | null;
    currency: string | null;
    trialEndsAt: string | null;
    endsAt: string | null;
    onGracePeriod: boolean;
};

export type BillingOverride = {
    source: string;
    reason: string;
    expiresAt: string | null;
};

export type BillingReadiness = {
    enabled: boolean;
    annualDisplay: boolean;
    checkoutReady: boolean;
    portalReady: boolean;
    missing: string[];
    promotionCodes: boolean;
};

export type BillingPageProps = {
    effectivePlan: BillingPlan;
    effectiveSource: string;
    override: BillingOverride | null;
    providerSubscription: ProviderSubscription | null;
    canManageBilling: boolean;
    plans: BillingPlan[];
    usage: Record<keyof PlanLimits, UsageMetric>;
    billing: BillingReadiness;
};
