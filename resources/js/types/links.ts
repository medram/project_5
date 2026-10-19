export type LinkPayload = {
    id: number;
    title: string | null;
    slug: string;
    destination_url: string;
    is_active: boolean;
    clicks_count: number;
    created_at: string;
    updated_at: string;
};

export type LinkListItem = {
    id: number;
    title: string | null;
    slug: string;
    destinationUrl: string;
    isActive: boolean;
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
        title: link.title,
        slug: link.slug,
        destinationUrl: link.destination_url,
        isActive: link.is_active,
        clicksCount: link.clicks_count,
        createdAt: link.created_at,
        updatedAt: link.updated_at,
    };
}
