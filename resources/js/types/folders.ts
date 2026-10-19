export type FolderOption = {
    id: number;
    name: string;
};

export type FolderPayload = FolderOption & {
    links_count: number;
    clicks_count: number;
    created_at: string;
    updated_at: string;
};

export type FolderIndexProps = {
    folders: FolderPayload[];
};
