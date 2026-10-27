export type AnalyticsWindowDays = 7 | 30 | 90;
export type AnalyticsMetric = 'engagements' | 'events';

export type AnalyticsResourceType =
    | 'link'
    | 'bio_page'
    | 'bio_link'
    | 'link_page'
    | 'link_page_variant'
    | 'link_page_item'
    | 'link_overlay';

export type AnalyticsFilters = {
    days: AnalyticsWindowDays;
    resource_type: AnalyticsResourceType | null;
    resource_id: number | null;
    event_name: string | null;
    browser: string | null;
    platform: string | null;
    country: string | null;
    utm_source: string | null;
    utm_medium: string | null;
    utm_campaign: string | null;
    utm_term: string | null;
    utm_content: string | null;
};

export type AnalyticsDaily = {
    date: string;
    clicks: number;
    events: number;
};

export type AnalyticsCount = {
    name: string;
    count: number;
};

export type AnalyticsInsightsResult = {
    days: AnalyticsWindowDays;
    totalClicks: number;
    uniqueVisitors: number;
    totalEvents: number;
    daily: AnalyticsDaily[];
    eventsByName: AnalyticsCount[];
    topReferrers: AnalyticsCount[];
    topCountries: AnalyticsCount[];
    topBrowsers: AnalyticsCount[];
    topPlatforms: AnalyticsCount[];
};

export type AnalyticsEventRecord = {
    uuid: string;
    event_name: string;
    occurred_at: string;
    link_id: number | null;
    bio_page_id: number | null;
    bio_link_id: number | null;
    link_page_id: number | null;
    link_page_variant_id: number | null;
    link_page_item_id: number | null;
    link_overlay_id: number | null;
    country_code: string | null;
    browser: string | null;
    platform: string | null;
    referrer_host: string | null;
    utm_source: string | null;
    utm_medium: string | null;
    utm_campaign: string | null;
    utm_term: string | null;
    utm_content: string | null;
};

export type AnalyticsEventPageProps = {
    filters: AnalyticsFilters;
    events: {
        data: AnalyticsEventRecord[];
        links: { url: string | null; label: string; active: boolean }[];
        current_page: number;
        last_page: number;
        total: number;
    };
};

export type AnalyticsCustomEventDefinition = {
    id: number;
    event_name: string;
    is_active: boolean;
};

export type AnalyticsCustomEventsProps = {
    definitions: AnalyticsCustomEventDefinition[];
};

export type AccountAnalyticsProps = {
    analytics: AnalyticsInsightsResult;
    filters: AnalyticsFilters;
    canManageCustomEvents: boolean;
};
