export type BioPagePayload = {
    id: number;
    handle: string;
    display_name: string;
    bio: string | null;
    is_published: boolean;
    created_at: string;
    updated_at: string;
};

export type BioLinkPayload = {
    id: number;
    bio_page_id: number;
    title: string;
    destination_url: string;
    position: number;
    is_active: boolean;
    created_at: string;
    updated_at: string;
};

export type BioPage = {
    id: number;
    handle: string;
    displayName: string;
    bio: string | null;
    isPublished: boolean;
    createdAt: string;
    updatedAt: string;
};

export type BioLink = {
    id: number;
    bioPageId: number;
    title: string;
    destinationUrl: string;
    position: number;
    isActive: boolean;
    createdAt: string;
    updatedAt: string;
};

export type BioPageIndexProps = {
    bioPages: BioPagePayload[];
};

export type BioPageEditorProps = {
    bioPage: BioPagePayload;
    bioLinks: BioLinkPayload[];
};

export type BioPagePublicLink = {
    id: number;
    title: string;
    url: string;
};

export type BioPagePublicProps = {
    displayName: string;
    bio: string | null;
    handle: string;
    links: BioPagePublicLink[];
    trackingConsent: 'granted' | 'denied' | null;
    privacyUrl: string | null;
};

export function toBioPage(bioPage: BioPagePayload): BioPage {
    return {
        id: bioPage.id,
        handle: bioPage.handle,
        displayName: bioPage.display_name,
        bio: bioPage.bio,
        isPublished: bioPage.is_published,
        createdAt: bioPage.created_at,
        updatedAt: bioPage.updated_at,
    };
}

export function toBioLink(bioLink: BioLinkPayload): BioLink {
    return {
        id: bioLink.id,
        bioPageId: bioLink.bio_page_id,
        title: bioLink.title,
        destinationUrl: bioLink.destination_url,
        position: bioLink.position,
        isActive: bioLink.is_active,
        createdAt: bioLink.created_at,
        updatedAt: bioLink.updated_at,
    };
}
