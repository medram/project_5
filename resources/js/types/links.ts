import type { FolderOption } from '@/types/folders';

export type LinkPayload = {
    id: number;
    folder_id: number | null;
    folder: FolderOption | null;
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
};

export type LinkInsightProps = {
    link: LinkListItem;
    totalClicks: number;
    dailyClicks: DailyClick[];
};

export type LinkInsightPageProps = Omit<LinkInsightProps, 'link'> & {
    link: LinkPayload;
};

export function toLinkListItem(link: LinkPayload): LinkListItem {
    return {
        id: link.id,
        folderId: link.folder_id,
        folder: link.folder,
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
