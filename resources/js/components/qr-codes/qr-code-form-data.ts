import type { QrCodeCampaign } from '@/types/qr-codes';

export type QrErrorCorrection = 'low' | 'medium' | 'quartile' | 'high';

export type QrCodeFormData = {
    target_type: 'url' | 'link';
    link_id: string;
    destination_url: string;
    title: string;
    share_title: string;
    share_description: string;
    is_active: boolean;
    available_from: string;
    expires_at: string;
    max_scans: string;
    access_code: string;
    remove_access_code: boolean;
    foreground_color: string;
    background_color: string;
    error_correction: QrErrorCorrection;
    margin: string;
    size: string;
    logo: File | null;
    logo_alt: string;
    clear_logo: boolean;
    _method?: 'PATCH';
};

function localDateTime(value: string | null): string {
    if (!value) return '';
    const date = new Date(value);
    return new Date(date.getTime() - date.getTimezoneOffset() * 60_000)
        .toISOString()
        .slice(0, 16);
}

export function qrCodeFormData(campaign?: QrCodeCampaign): QrCodeFormData {
    return {
        target_type: campaign?.link_id ? 'link' : 'url',
        link_id: campaign?.link_id ? String(campaign.link_id) : '',
        destination_url: campaign?.destination_url ?? '',
        title: campaign?.title ?? '',
        share_title: campaign?.share_title ?? '',
        share_description: campaign?.share_description ?? '',
        is_active: campaign?.is_active ?? true,
        available_from: localDateTime(campaign?.available_from ?? null),
        expires_at: localDateTime(campaign?.expires_at ?? null),
        max_scans: campaign?.max_scans ? String(campaign.max_scans) : '',
        access_code: '',
        remove_access_code: false,
        foreground_color: campaign?.foreground_color ?? '#000000',
        background_color: campaign?.background_color ?? '#FFFFFF',
        error_correction: campaign?.error_correction ?? 'medium',
        margin: String(campaign?.margin ?? 4),
        size: String(campaign?.size ?? 320),
        logo: null,
        logo_alt: campaign?.logo_alt ?? '',
        clear_logo: false,
    };
}

export function toServerDate(value: string): string | null {
    return value === '' ? null : new Date(value).toISOString();
}
