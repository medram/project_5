import type { AnalyticsWindows } from '@/types/analytics';
import type { CustomDomainOption } from '@/types/custom-domains';
import type { FolderOption } from '@/types/folders';
import type { LinkOverlayOption } from '@/types/overlays';
export type LinkPayload = {
    id: number;
    folder_id: number | null;
    folder: FolderOption | null;
    custom_domain_id: number | null;
    custom_domain: CustomDomainOption | null;
    link_overlay_id: number | null;
    link_overlay: LinkOverlayOption | null;
    title: string | null;
    slug: string;
    destination_url: string;
    is_active: boolean;
    available_from: string | null;
    expires_at: string | null;
    has_access_code: boolean;
    clicks_count: number;
    created_at: string;
    updated_at: string;
};

export type LinkListItem = {
    id: number;
    folderId: number | null;
    folder: FolderOption | null;
    customDomainId: number | null;
    customDomain: CustomDomainOption | null;
    linkOverlayId: number | null;
    linkOverlay: LinkOverlayOption | null;
    title: string | null;
    slug: string;
    destinationUrl: string;
    isActive: boolean;
    availableFrom: string | null;
    expiresAt: string | null;
    hasAccessCode: boolean;
    clicksCount: number;
    createdAt: string;
    updatedAt: string;
};

export type DailyClick = {
    date: string;
    clicks: number;
};

export type LinkIndexProps = {
    links: LinkPayload[];
    folders: FolderOption[];
    customDomains: CustomDomainOption[];
    linkOverlays: LinkOverlayOption[];
};

export type LinkInsightProps = {
    link: LinkListItem;
    totalClicks: number;
    dailyClicks: DailyClick[];
    analyticsWindows: AnalyticsWindows;
};

export type LinkInsightPageProps = Omit<LinkInsightProps, 'link'> & {
    link: LinkPayload;
};

export function toLinkListItem(link: LinkPayload): LinkListItem {
    return {
        id: link.id,
        folderId: link.folder_id,
        folder: link.folder,
        customDomainId: link.custom_domain_id,
        customDomain: link.custom_domain,
        linkOverlayId: link.link_overlay_id,
        linkOverlay: link.link_overlay,
        title: link.title,
        slug: link.slug,
        destinationUrl: link.destination_url,
        isActive: link.is_active,
        availableFrom: link.available_from,
        expiresAt: link.expires_at,
        hasAccessCode: link.has_access_code,
        clicksCount: link.clicks_count,
        createdAt: link.created_at,
        updatedAt: link.updated_at,
    };
}
