import type { LinkListItem } from '@/types/links';

export type LinkStatus = {
    label: 'Archived' | 'Pending review' | 'Live' | 'Paused';
    variant: 'secondary' | 'warning' | 'success';
    dot: string;
};

export function linkStatus(
    link: Pick<LinkListItem, 'archivedAt' | 'approvalStatus' | 'isActive'>,
): LinkStatus {
    if (link.archivedAt) {
        return {
            label: 'Archived',
            variant: 'secondary',
            dot: 'bg-muted-foreground',
        };
    }

    if (link.approvalStatus === 'pending') {
        return {
            label: 'Pending review',
            variant: 'warning',
            dot: 'bg-warning',
        };
    }

    if (link.isActive) {
        return { label: 'Live', variant: 'success', dot: 'bg-success' };
    }

    return { label: 'Paused', variant: 'warning', dot: 'bg-warning' };
}