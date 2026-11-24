export type User = {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type PlatformBranding = {
    siteName: string;
    supportEmail: string | null;
    homepage: string;
    defaultDelivery: 'direct' | 'splash';
    logos: {
        light: string | null;
        dark: string | null;
        compact: string | null;
        favicon: string | null;
    };
    theme: { scheme: string; accent: string; radius: string };
    menus: {
        header: Array<{ label: string; href: string }>;
        footer: Array<{ label: string; href: string }>;
    };
    visibility: {
        pricing: boolean;
        contact: boolean;
        registration: boolean;
    };
    seo: {
        title: string | null;
        description: string | null;
        homeTitle: string | null;
        homeDescription: string | null;
        pricingTitle: string | null;
        pricingDescription: string | null;
        ogImage: string | null;
    };
    availableBlocks: string[];
    capabilities: { targeting: boolean; pixels: boolean; domains: boolean };
    maintenanceBanner: string | null;
    verification: Record<string, string>;
    gdpr: {
        notice: string | null;
        position: string;
        defaultDenied: boolean;
        privacyUrl: string | null;
        termsUrl: string | null;
    };
    analytics: { consentEnabled: boolean; ga4: string | null };
    captcha: { enabled: boolean; siteKey: string | null };
    ads: {
        provider: string;
        publisherId: string | null;
        headerSlot: string | null;
        contentSlot: string | null;
        consentRequired: boolean;
    } | null;
};

export type Auth = {
    user: User | null;
    isAdmin: boolean;
    canSearchPlatform: boolean;
    adminAbilities: string[];
};

export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};

export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};
