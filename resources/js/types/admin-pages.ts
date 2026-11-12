export type AdminCustomPageRow = {
    id: number;
    slug: string;
    title: string;
    state: string;
    placement: string | null;
    creator: string | null;
    updater: string | null;
    publishedAt: string | null;
    updatedAt: string | null;
};

export type AdminCustomPage = {
    id: number;
    slug: string;
    title: string;
    body: string;
    state: string;
    placement: string | null;
    seo_title: string | null;
    seo_description: string | null;
    og_media_id: number | null;
    published_at: string | null;
};

export type AdminPagesPageProps = {
    pages: {
        data: AdminCustomPageRow[];
        current_page: number;
        last_page: number;
        total: number;
        from: number | null;
        to: number | null;
        links: Array<{
            url: string | null;
            label: string;
            active: boolean;
        }>;
    };
    filters: {
        q: string;
        state: string | null;
        placement: string | null;
    };
};

export type AdminPageFormProps = {
    page: AdminCustomPage | null;
};
