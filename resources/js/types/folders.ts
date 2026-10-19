export type FolderPayload = {
    id: number;
    name: string;
    links_count: number;
    clicks_count: number;
    created_at: string;
    updated_at: string;
};

export type FolderIndexProps = {
    folders: FolderPayload[];
};
