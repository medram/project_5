export type AdminContentRow = {
    name: string;
    identifier: string | null;
    workspace: string | null;
    owner: string | null;
    state: string;
    type: string;
    provider: string | null;
    counts: Array<{
        label: string;
        value: number;
    }>;
    createdAt: string | null;
    updatedAt: string | null;
    extra: Record<string, string | null>;
};

export type AdminContentResource = {
    key: string;
    title: string;
    description: string;
    identifierLabel: string | null;
    stateOptions: Array<{
        value: string;
        label: string;
    }>;
};

export type AdminContentPageProps = {
    resource: AdminContentResource;
    rows: {
        data: AdminContentRow[];
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
        sort: string;
        direction: string;
    };
};
