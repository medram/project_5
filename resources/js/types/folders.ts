import type { PaginatedCollection } from '@/types/pagination';
export type FolderOption = {
    id: number;
    name: string;
};

export type FolderPayload = FolderOption & {
    links_count: number;
    clicks_count: number;
    analytics_events_count: number;
    updated_at: string;
};

export type FolderIndexProps = {
    folders: PaginatedCollection<FolderPayload>;
    filters: {
        search: string;
        sort: 'name' | 'created_at' | 'links_count';
        direction: 'asc' | 'desc';
        page: string;
        view: 'table' | 'cards';
    };
};
