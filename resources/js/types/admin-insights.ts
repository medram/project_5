export type AdminInsightsRange = {
    preset: string;
    from: string;
    to: string;
    days: number;
    label: string;
};

export type AdminDimension = {
    name: string;
    count: number;
};

export type AdminPlatformDaily = {
    date: string;
    users: number;
    workspaces: number;
    clicks: number;
    scans: number;
    events: number;
};

export type AdminVisitorDaily = {
    date: string;
    events: number;
    uniqueVisitors: number;
    clicks: number;
    scans: number;
    pageViews: number;
};

export type AdminInsightsDimensions = {
    countries: AdminDimension[];
    browsers: AdminDimension[];
    platforms: AdminDimension[];
    devices: AdminDimension[];
    referrers: AdminDimension[];
};

export type AdminPlatformInsights = {
    range: AdminInsightsRange;
    accounts: {
        total: number;
        new: number;
        active: number;
        suspended: number;
        deletionPending: number;
    };
    workspaces: {
        total: number;
        new: number;
    };
    subscriptions: {
        states: Array<{ state: string; count: number }>;
        mrrCents: number;
        arrCents: number;
        mappedSubscriptions: number;
        unmappedSubscriptions: number;
        source: string;
    };
    resources: {
        links: number;
        folders: number;
        qrCampaigns: number;
        bioPages: number;
        domains: number;
        pixels: number;
        members: number;
        webhooks: number;
        linkPages: number;
        apiKeys: number;
        storageBytes: number;
    };
    engagement: {
        clicks: number;
        scans: number;
        events: number;
    };
    daily: AdminPlatformDaily[];
    dimensions: AdminInsightsDimensions;
    kAnonymityThreshold: number;
};

export type AdminVisitorInsights = {
    range: AdminInsightsRange;
    totals: {
        events: number;
        uniqueVisitors: number;
        clicks: number;
        scans: number;
        pageViews: number;
    };
    daily: AdminVisitorDaily[];
    eventSeries: AdminDimension[];
    dimensions: AdminInsightsDimensions;
    kAnonymityThreshold: number;
};

export type AdminPlatformInsightsPageProps = {
    insights: AdminPlatformInsights;
};

export type AdminVisitorInsightsPageProps = {
    insights: AdminVisitorInsights;
};
