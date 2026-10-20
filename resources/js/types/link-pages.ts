export type TrackingPixelProvider = 'meta_pixel' | 'google_analytics';

export type TrackingPixel = {
    id: number;
    name: string;
    provider: TrackingPixelProvider;
    providerId: string;
    isActive: boolean;
    linkPagesCount?: number;
};

export type TrackingPixelsPageProps = {
    pixels: TrackingPixel[];
};

export type LinkPage = {
    id: number;
    name: string;
    slug: string;
    title: string;
    description: string | null;
    isPublished: boolean;
    itemsCount: number;
    trackingPixelIds: number[];
    publicUrl: string;
};

export type LinkPageItem = {
    id: number;
    title: string;
    destinationUrl: string;
    position: number;
    isActive: boolean;
};

export type LinkPageIndexProps = {
    linkPages: LinkPage[];
};

export type LinkPageEditorProps = {
    linkPage: LinkPage;
    items: LinkPageItem[];
    availablePixels: TrackingPixel[];
};

export type PublicTrackingPixel = {
    provider: TrackingPixelProvider;
    identifier: string;
};

export type LinkPagePublicItem = {
    id: number;
    title: string;
    url: string;
};

export type LinkPagePublicProps = {
    name: string;
    title: string;
    description: string | null;
    slug: string;
    items: LinkPagePublicItem[];
    pixels: PublicTrackingPixel[];
    trackingConsent: 'granted' | 'denied' | null;
    privacyUrl: string | null;
};
