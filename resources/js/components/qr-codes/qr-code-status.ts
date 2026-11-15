import type { QrCodeCampaign } from '@/types/qr-codes';

export type QrCampaignState =
    | 'Active'
    | 'Paused'
    | 'Scheduled'
    | 'Expired'
    | 'Limit reached';

export type QrCampaignBadgeVariant = 'success' | 'warning' | 'secondary';

export function qrCampaignState(campaign: QrCodeCampaign): QrCampaignState {
    const now = Date.now();

    if (!campaign.is_active) {
        return 'Paused';
    }

    if (
        campaign.available_from &&
        new Date(campaign.available_from).getTime() > now
    ) {
        return 'Scheduled';
    }

    if (campaign.expires_at && new Date(campaign.expires_at).getTime() <= now) {
        return 'Expired';
    }

    if (
        campaign.max_scans !== null &&
        campaign.scan_count >= campaign.max_scans
    ) {
        return 'Limit reached';
    }

    return 'Active';
}

export function qrCampaignBadgeVariant(
    state: QrCampaignState,
): QrCampaignBadgeVariant {
    if (state === 'Active') {
        return 'success';
    }

    if (
        state === 'Paused' ||
        state === 'Scheduled' ||
        state === 'Limit reached'
    ) {
        return 'warning';
    }

    return 'secondary';
}
