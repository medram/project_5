import type { PublicTrackingPixelsByTrigger } from '@/types/link-pages';

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
    startsAt: string | null;
    expiresAt: string | null;
    displayDelaySeconds: number;
    linksCount: number;
};

export type OverlaysPageProps = {
    overlays: LinkOverlay[];
    canManageIntegrations: boolean;
};

export type OverlayPublicProps = {
    slug: string;
    linkTitle: string | null;
    overlay: {
        heading: string;
        body: string | null;
        ctaLabel: string;
    };
    pixels: PublicTrackingPixelsByTrigger;
    trackingConsent: 'granted' | 'denied' | null;
    privacyUrl: string | null;
    canGrantConsent: boolean;
    remainingDelaySeconds: number;
};
