import type { PublicTrackingPixelsByTrigger } from '@/types/link-pages';

export type BioPageAppearance = {
    theme: 'light' | 'dark' | 'soft';
    typography: 'sans' | 'serif';
    color: 'blue' | 'violet' | 'green' | 'amber' | 'rose';
    background: 'plain' | 'sand' | 'lavender' | 'midnight';
};
export type BioPagePayload = {
    id: number;
    handle: string;
    display_name: string;
    bio: string | null;
    is_published: boolean;
    appearance?: BioPageAppearance;
    created_at: string;
    updated_at: string;
};
export type BioLinkPayload = {
    id: number;
    bio_page_id: number;
    title: string;
    destination_url: string;
    position: number;
    is_active: boolean;
    is_pinned?: boolean;
    starts_at?: string | null;
    ends_at?: string | null;
    created_at: string;
    updated_at: string;
};
export type BioPage = {
    id: number;
    handle: string;
    displayName: string;
    bio: string | null;
    isPublished: boolean;
    appearance: BioPageAppearance;
    createdAt: string;
    updatedAt: string;
};
export type BioLink = {
    id: number;
    bioPageId: number;
    title: string;
    destinationUrl: string;
    position: number;
    isActive: boolean;
    isPinned: boolean;
    startsAt: string | null;
    endsAt: string | null;
    createdAt: string;
    updatedAt: string;
};
export type BioPageIndexProps = { bioPages: BioPagePayload[] };
export type BioPageInsightCount = {
    name: string;
    count: number;
};
export type BioPageInsights = {
    days: 7 | 30 | 90;
    startDate: string;
    endDate: string;
    totalViews: number;
    totalClicks: number;
    daily: { date: string; views: number; clicks: number }[];
    topLinks: BioPageInsightCount[];
    topReferrers: BioPageInsightCount[];
    topCountries: BioPageInsightCount[];
    topBrowsers: BioPageInsightCount[];
    topPlatforms: BioPageInsightCount[];
};
export type BioPageEditorProps = {
    bioPage: BioPagePayload;
    bioLinks: BioLinkPayload[];
    contentBlocks?: BioContentBlockPayload[];
    previewAt: string;
    insights?: BioPageInsights;
};
type BioPageItemBase = {
    id: number;
    position: number;
    isActive: boolean;
    isPinned: boolean;
    startsAt: string | null;
    endsAt: string | null;
};
export type BioPageItem =
    | (BioPageItemBase & { type: 'link'; title: string; url: string })
    | (BioPageItemBase & { type: 'text'; text: string })
    | (BioPageItemBase & {
          type: 'image';
          altText: string;
          imageUrl: string | null;
      })
    | (BioPageItemBase & {
          type: 'social';
          provider: string;
          identifier: string;
          url: string;
      })
    | (BioPageItemBase & {
          type: 'embed';
          provider: string;
          identifier: string;
          embedUrl: string;
          providerType?: string;
      });
export type BioContentBlockPayload = Exclude<BioPageItem, { type: 'link' }>;
export type BioPagePresentationModel = {
    handle: string;
    displayName: string;
    bio: string | null;
    appearance: BioPageAppearance;
    items: BioPageItem[];
};
export type BioPagePublicProps = {
    presentation: BioPagePresentationModel;
    pixels: PublicTrackingPixelsByTrigger;
    trackingConsent: 'granted' | 'denied' | null;
    privacyUrl: string | null;
    canGrantConsent: boolean;
    branding: { label: string; logo: string | null } | null;
};
export function toBioPage(bioPage: BioPagePayload): BioPage {
    return {
        id: bioPage.id,
        handle: bioPage.handle,
        displayName: bioPage.display_name,
        bio: bioPage.bio,
        isPublished: bioPage.is_published,
        appearance: {
            theme: 'light',
            typography: 'sans',
            color: 'blue',
            background: 'plain',
            ...bioPage.appearance,
        },
        createdAt: bioPage.created_at,
        updatedAt: bioPage.updated_at,
    };
}
export function toBioLink(link: BioLinkPayload): BioLink {
    return {
        id: link.id,
        bioPageId: link.bio_page_id,
        title: link.title,
        destinationUrl: link.destination_url,
        position: link.position,
        isActive: link.is_active,
        isPinned: link.is_pinned ?? false,
        startsAt: link.starts_at ?? null,
        endsAt: link.ends_at ?? null,
        createdAt: link.created_at,
        updatedAt: link.updated_at,
    };
}
