export type PlanLimits = {
    links: number | null;
    domains: number | null;
    pixels: number | null;
    webhooks: number | null;
    linkPages: number | null;
};

export type BillingPlan = {
    code: string;
    name: string;
    monthlyPrice: number;
    currency: string;
    limits: PlanLimits;
};

export type Subscription = {
    status: string;
    source: string;
    plan: BillingPlan;
};

export type UsageMetric = {
    label: string;
    used: number;
    limit: number | null;
    overLimit: boolean;
};

export type BillingPageProps = {
    subscription: Subscription;
    plans: BillingPlan[];
    usage: {
        links: UsageMetric;
    };
};
