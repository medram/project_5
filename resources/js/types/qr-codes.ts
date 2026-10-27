export type QrCodeLinkOption = {
    id: number;
    slug: string;
    title: string | null;
    destination_url: string;
};

export type QrCodeCampaign = {
    id: number;
    code: string;
    source: 'linked' | 'campaign';
    title: string | null;
    destination_url: string | null;
    link_id: number | null;
    link: { id: number; slug: string; title: string | null } | null;
    share_title: string | null;
    share_description: string | null;
    is_active: boolean;
    available_from: string | null;
    expires_at: string | null;
    max_scans: number | null;
    scan_count: number;
    scans_count: number;
    foreground_color: string;
    background_color: string;
    error_correction: 'low' | 'medium' | 'quartile' | 'high';
    margin: number;
    size: number;
    has_access_code: boolean;
    has_logo: boolean;
    logo_alt: string | null;
};

export type QrCodeIndexProps = {
    campaigns: QrCodeCampaign[];
    availableLinks: QrCodeLinkOption[];
};

export type QrCodeAnalytics = {
    days: 7 | 30 | 90;
    totalScans: number;
    daily: { date: string; scans: number }[];
};

export type QrCodeInsightsProps = {
    scope: 'workspace' | 'campaign';
    qrCode: QrCodeCampaign | null;
    analytics: QrCodeAnalytics;
};
