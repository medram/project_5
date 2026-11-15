export type OperationLogRow = {
    id: number;
    level: string;
    channel: string;
    event: string;
    message: string;
    context: Record<string, unknown> | null;
    correlationId: string | null;
    createdAt: string | null;
};

export type OperationLogsPageProps = {
    rows: {
        data: OperationLogRow[];
        current_page: number;
        last_page: number;
        total: number;
        from: number | null;
        to: number | null;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    filters: { q: string; level: string | null; channel: string | null };
};
