export type QrCodeLink = {
    id: number;
    title: string | null;
    slug: string;
    destination_url: string;
    clicks_count: number;
};

export type QrCodeIndexProps = {
    links: QrCodeLink[];
};
