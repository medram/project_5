import {
    Activity,
    FileText,
    Folder,
    Globe2,
    HardDrive,
    KeyRound,
    Link2,
    PanelsTopLeft,
    QrCode,
    Users,
    Webhook,
    type LucideIcon,
} from 'lucide-react';
import type { PlanLimits, UsageMetric } from '@/types/billing';

export const usageOrder: Array<{ key: keyof PlanLimits; noun: string }> = [
    { key: 'links', noun: 'links' },
    { key: 'folders', noun: 'folders' },
    { key: 'qr_campaigns', noun: 'direct QR campaigns' },
    { key: 'bio_pages', noun: 'bio pages' },
    { key: 'domains', noun: 'custom domains' },
    { key: 'pixels', noun: 'tracking pixels' },
    { key: 'members', noun: 'members' },
    { key: 'webhooks', noun: 'webhooks' },
    { key: 'link_pages', noun: 'link pages' },
    { key: 'api_keys', noun: 'active API keys' },
    { key: 'storage_bytes', noun: 'retained media bytes' },
];

export function formatBytes(bytes: number): string {
    if (bytes >= 1_073_741_824) {
        return `${(bytes / 1_073_741_824).toLocaleString(undefined, {
            maximumFractionDigits: 1,
        })} GB`;
    }

    if (bytes >= 1_048_576) {
        return `${(bytes / 1_048_576).toLocaleString(undefined, {
            maximumFractionDigits: 1,
        })} MB`;
    }

    return `${bytes.toLocaleString()} B`;
}

export function limitLabel(
    key: keyof PlanLimits,
    value: number | null,
): string {
    if (value === null) {
        return 'Unlimited';
    }

    return key === 'storage_bytes'
        ? formatBytes(value)
        : value.toLocaleString();
}

export const metricIcons: Record<keyof PlanLimits, LucideIcon> = {
    links: Link2,
    folders: Folder,
    qr_campaigns: QrCode,
    bio_pages: PanelsTopLeft,
    domains: Globe2,
    pixels: Activity,
    members: Users,
    webhooks: Webhook,
    link_pages: FileText,
    api_keys: KeyRound,
    storage_bytes: HardDrive,
};

export function UsageRow({
    metric,
    noun,
    metricKey,
}: {
    metric: UsageMetric;
    noun: string;
    metricKey: keyof PlanLimits;
}) {
    const limit = metric.limit;
    const percent =
        limit === null || limit === 0
            ? 0
            : Math.min(100, Math.round((metric.used / limit) * 100));

    const MetricIcon = metricIcons[metricKey];

    return (
        <div className="grid gap-1.5 py-2.5">
            <div className="flex items-center justify-between gap-4">
                <span className="flex min-w-0 items-center gap-2 text-sm">
                    <MetricIcon
                        className="size-4 shrink-0 text-muted-foreground"
                        aria-hidden="true"
                    />
                    {metric.label}
                </span>
                <span className="shrink-0 text-xs whitespace-nowrap text-muted-foreground tabular-nums">
                    {metricKey === 'storage_bytes'
                        ? formatBytes(metric.used)
                        : metric.used.toLocaleString()}{' '}
                    / {limitLabel(metricKey, limit)}
                </span>
            </div>
            <div
                aria-hidden="true"
                className="h-1 overflow-hidden rounded-full bg-muted"
            >
                <div
                    className={`h-full rounded-full ${metric.overLimit ? 'bg-destructive' : 'bg-primary'}`}
                    style={{ width: limit === null ? '0%' : `${percent}%` }}
                />
            </div>
            {metric.overLimit && (
                <p className="text-xs text-destructive">
                    New {noun} are blocked until usage is back within this
                    plan’s limit.
                </p>
            )}
        </div>
    );
}
