export type AdminFileRow = {
    id: number;
    originalName: string;
    workspace: string | null;
    owner: string | null;
    uploader: string | null;
    uploaderEmail: string | null;
    purpose: string;
    mimeType: string;
    byteSize: number;
    sha256: string;
    visibility: string;
    scanState: string;
    expiresAt: string | null;
    deletedAt: string | null;
    deletionReason: string | null;
    createdAt: string | null;
    updatedAt: string | null;
};

export type AdminFilesPageProps = {
    rows: {
        data: AdminFileRow[];
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
        purpose: string | null;
        scan: string | null;
        state: string | null;
        sort: string;
        direction: string;
    };
    purposes: Array<{
        value: string;
        label: string;
    }>;
};
