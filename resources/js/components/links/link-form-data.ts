import type { LinkDeliveryMode, LinkListItem } from '@/types/links';

export type RoutingRuleInput = {
    rule_type: 'country' | 'device' | 'platform';
    match_value: string;
    target_url: string;
};

export type CustomParameterInput = {
    key: string;
    value: string;
};

export type LinkFormData = {
    destination_url: string;
    delivery_mode: LinkDeliveryMode;
    link_page_id: string;
    title: string;
    slug: string;
    is_active: boolean;
    folder_id: string;
    custom_domain_id: string;
    link_overlay_id: string;
    available_from: string;
    expires_at: string;
    access_code: string;
    clear_access_code: boolean;
    tag_ids: number[];
    max_clicks: string;
    approval_required: boolean;
    utm_parameters: Record<string, string>;
    custom_parameters: CustomParameterInput[];
    routing_rules: RoutingRuleInput[];
};

export function dateTimeLocalValue(value: string | null | undefined): string {
    if (!value) {
        return '';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '';
    }

    return new Date(date.getTime() - date.getTimezoneOffset() * 60_000)
        .toISOString()
        .slice(0, 16);
}

export function dateTimeUtcValue(value: string): string {
    if (value === '') {
        return '';
    }

    const date = new Date(value);

    return Number.isNaN(date.getTime()) ? value : date.toISOString();
}

export function formDataFor(link?: LinkListItem): LinkFormData {
    return {
        destination_url: link?.destinationUrl ?? '',
        delivery_mode: link?.deliveryMode ?? 'direct',
        link_page_id: link?.linkPageId?.toString() ?? '',
        title: link?.title ?? '',
        slug: link?.slug ?? '',
        is_active: link?.isActive ?? true,
        folder_id: link?.folderId?.toString() ?? '',
        custom_domain_id: link?.customDomainId?.toString() ?? '',
        link_overlay_id: link?.linkOverlayId?.toString() ?? '',
        available_from: dateTimeLocalValue(link?.availableFrom),
        expires_at: dateTimeLocalValue(link?.expiresAt),
        access_code: '',
        clear_access_code: false,
        tag_ids: link?.tags.map((tag) => tag.id) ?? [],
        max_clicks: link?.maxClicks?.toString() ?? '',
        approval_required: link?.approvalRequired ?? false,
        utm_parameters: link?.utmParameters ?? {},
        custom_parameters: Object.entries(link?.customParameters ?? {}).map(
            ([key, value]) => ({ key, value }),
        ),
        routing_rules: (link?.routingRules ?? []).map((rule) => ({
            rule_type: rule.rule_type,
            match_value: rule.match_value,
            target_url: rule.target_url,
        })),
    };
}

export function withListQuery(url: string, listQuery: string): string {
    return listQuery === '' ? url : `${url}?${listQuery}`;
}

export function previewDestination(
    destinationUrl: string,
    utmParameters: Record<string, string>,
    customParameters: CustomParameterInput[],
): string {
    if (!destinationUrl) {
        return '';
    }

    try {
        const preview = new URL(destinationUrl);
        const parameters = new Map<string, string>(
            Object.entries(utmParameters).filter(
                ([, value]) => value.trim() !== '',
            ),
        );
        for (const { key, value } of customParameters) {
            const trimmedKey = key.trim();
            if (trimmedKey !== '' && value !== '') {
                parameters.set(trimmedKey, value);
            }
        }

        for (const key of parameters.keys()) {
            preview.searchParams.delete(key);
        }
        for (const [key, value] of parameters) {
            preview.searchParams.append(key, value);
        }

        return preview.toString();
    } catch {
        return '';
    }
}
