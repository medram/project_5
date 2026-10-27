import type { AnalyticsInsightsResult } from '@/types/analytics';
import type { CustomDomainOption } from '@/types/custom-domains';
import type { FolderOption } from '@/types/folders';
import type { LinkOverlayOption } from '@/types/overlays';
import type { PaginatedCollection } from '@/types/pagination';

export type LinkPageOption = {
    id: number;
    name: string;
    slug: string;
};

export type LinkDeliveryMode = 'direct' | 'splash' | 'link_page' | 'overlay';

export type LinkApprovalStatus = 'approved' | 'pending';

export type LinkTagOption = {
    id: number;
    name: string;
    slug: string;
    color: string | null;
};

export type LinkRoutingRule = {
    id: number;
    workspace_id: number;
    link_id: number;
    position: number;
    rule_type: 'country' | 'device' | 'platform';
    match_value: string;
    target_url: string;
};

export type LinkPayload = {
    id: number;
    folder_id: number | null;
    folder: FolderOption | null;
    custom_domain_id: number | null;
    custom_domain: CustomDomainOption | null;
    link_overlay_id: number | null;
    link_overlay: LinkOverlayOption | null;
    link_page_id: number | null;
    link_page: LinkPageOption | null;
    delivery_mode: LinkDeliveryMode;
    routing_rules: LinkRoutingRule[];
    tags: LinkTagOption[];
    title: string | null;
    slug: string;
    destination_url: string | null;
    is_active: boolean;
    available_from: string | null;
    expires_at: string | null;
    archived_at: string | null;
    max_clicks: number | null;
    click_count: number;
    approval_required: boolean;
    approval_status: LinkApprovalStatus;
    has_pending_revision?: boolean;
    utm_parameters: Record<string, string> | null;
    custom_parameters: Record<string, string> | null;
    has_access_code: boolean;
    clicks_count: number;
    created_at: string;
    updated_at: string;
};

export type LinkListItem = {
    id: number;
    folderId: number | null;
    folder: FolderOption | null;
    customDomainId: number | null;
    customDomain: CustomDomainOption | null;
    linkOverlayId: number | null;
    linkOverlay: LinkOverlayOption | null;
    linkPageId: number | null;
    linkPage: LinkPageOption | null;
    deliveryMode: LinkDeliveryMode;
    routingRules: LinkRoutingRule[];
    tags: LinkTagOption[];
    title: string | null;
    slug: string;
    destinationUrl: string | null;
    isActive: boolean;
    availableFrom: string | null;
    expiresAt: string | null;
    archivedAt: string | null;
    maxClicks: number | null;
    clickCount: number;
    approvalRequired: boolean;
    approvalStatus: LinkApprovalStatus;
    hasPendingRevision: boolean;
    utmParameters: Record<string, string> | null;
    customParameters: Record<string, string> | null;
    hasAccessCode: boolean;
    clicksCount: number;
    createdAt: string;
    updatedAt: string;
};

export type LinkIndexProps = {
    links: PaginatedCollection<LinkPayload>;
    folders: FolderOption[];
    customDomains: CustomDomainOption[];
    linkOverlays: LinkOverlayOption[];
    linkPages: LinkPageOption[];
    tagOptions: LinkTagOption[];
    canManageApprovals: boolean;
    filters: {
        search: string;
        folder: string;
        status: string;
        tag: string;
        sort: 'created_at' | 'title' | 'clicks_count';
        direction: 'asc' | 'desc';
        page: string;
        view: 'table' | 'cards';
    };
};

export type LinkInsightPageProps = {
    link: LinkPayload;
    analytics: AnalyticsInsightsResult;
};

export type LinkRevisionRoutingRule = {
    position: number;
    condition: string;
    destination: string;
};

export type LinkRevisionSummary = {
    id: number;
    number: number;
    status: string;
    actor: string;
    reviewer: string | null;
    reviewNote: string | null;
    createdAt: string | null;
    reviewedAt: string | null;
    slug: string | null;
    title: string | null;
    deliveryMode: LinkDeliveryMode;
    target: string;
    routingRules: LinkRevisionRoutingRule[];
    pageLabel: string | null;
    overlayLabel: string | null;
    customDomainLabel: string | null;
    folderLabel: string | null;
    tagLabels: string[];
    isActive: boolean;
    availableFrom: string | null;
    expiresAt: string | null;
    maxClicks: number | null;
    approvalRequired: boolean;
    approvalStatus: string | null;
    utmParameters: Record<string, string>;
    customParameters: Record<string, string>;
    hasAccessCode: boolean;
};

export type LinkRevisionPageProps = {
    link: { id: number; slug: string; title: string | null };
    revisions: LinkRevisionSummary[];
    canReview: boolean;
    listQuery: string;
};

export function toLinkListItem(link: LinkPayload): LinkListItem {
    return {
        id: link.id,
        folderId: link.folder_id,
        folder: link.folder,
        customDomainId: link.custom_domain_id,
        customDomain: link.custom_domain,
        linkOverlayId: link.link_overlay_id,
        linkOverlay: link.link_overlay,
        linkPageId: link.link_page_id,
        linkPage: link.link_page,
        deliveryMode: link.delivery_mode,
        routingRules: link.routing_rules,
        tags: link.tags,
        title: link.title,
        slug: link.slug,
        destinationUrl: link.destination_url,
        isActive: link.is_active,
        availableFrom: link.available_from,
        expiresAt: link.expires_at,
        archivedAt: link.archived_at,
        maxClicks: link.max_clicks,
        clickCount: link.click_count,
        approvalRequired: link.approval_required,
        approvalStatus: link.approval_status,
        hasPendingRevision: link.has_pending_revision === true,
        utmParameters: link.utm_parameters,
        customParameters: link.custom_parameters,
        hasAccessCode: link.has_access_code,
        clicksCount: link.clicks_count,
        createdAt: link.created_at,
        updatedAt: link.updated_at,
    };
}
