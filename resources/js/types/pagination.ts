export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

export type PaginatedCollection<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: PaginationLink[];
};
