export type OverlayPixel = {
    id: number;
    name: string;
    provider: 'meta_pixel' | 'google_analytics';
    providerId: string;
    isActive: boolean;
};

export type LinkOverlayOption = {
    id: number;
    name: string;
};

export type LinkOverlay = {
    id: number;
    name: string;
    heading: string;
    body: string | null;
    ctaLabel: string;
    isActive: boolean;
    linksCount: number;
    trackingPixelIds: number[];
};

export type OverlaysPageProps = {
    overlays: LinkOverlay[];
    availablePixels: OverlayPixel[];
};

export type OverlayPublicProps = {
    slug: string;
    linkTitle: string | null;
    overlay: {
        heading: string;
        body: string | null;
        ctaLabel: string;
    };
    pixels: {
        provider: 'meta_pixel' | 'google_analytics';
        identifier: string;
    }[];
    trackingConsent: 'granted' | 'denied' | null;
    privacyUrl: string | null;
};
