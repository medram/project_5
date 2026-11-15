import { Head } from '@inertiajs/react';
import { ExternalLink } from 'lucide-react';
import { QrScanInsights } from '@/components/qr-codes/qr-scan-insights';
import {
    qrCampaignBadgeVariant,
    qrCampaignState,
} from '@/components/qr-codes/qr-code-status';
import { Badge } from '@/components/ui/badge';
import { Card, CardHeader } from '@/components/ui/card';
import { redirect as publicLink } from '@/routes/links';
import { campaignInsights, index, insights } from '@/routes/qr-codes';
import type { AnalyticsWindowDays } from '@/types/analytics';
import type { QrCodeInsightsProps } from '@/types/qr-codes';

export default function QrCodeInsights({
    scope,
    qrCode,
    analytics,
}: QrCodeInsightsProps) {
    const currentUrl =
        scope === 'campaign' && qrCode
            ? campaignInsights(qrCode.id).url
            : insights().url;
    const title =
        scope === 'campaign'
            ? qrCode?.title || qrCode?.link?.title || 'QR campaign scans'
            : 'Workspace QR scans';
    const destinationLabel = qrCode
        ? qrCode.link
            ? `/${qrCode.link.slug}`
            : (qrCode.destination_url ?? 'No destination')
        : null;
    const destinationUrl = qrCode
        ? qrCode.link
            ? publicLink(qrCode.link.slug).url
            : qrCode.destination_url
        : null;
    const state = qrCode ? qrCampaignState(qrCode) : null;

    function windowUrl(days: AnalyticsWindowDays): string {
        return `${currentUrl}?days=${days}`;
    }

    return (
        <>
            <Head title={`${title} · QR insights`} />
            <section className="mx-auto flex w-full max-w-7xl min-w-0 flex-1 flex-col gap-5 px-5 pt-4 pb-10">
                <h1 className="sr-only">{title}</h1>

                {qrCode && state && (
                    <Card className="py-3">
                        <CardHeader>
                            <div className="flex min-w-0 flex-wrap items-center gap-x-3 gap-y-2">
                                <div className="flex min-w-0 flex-1 items-baseline gap-2">
                                    <span className="shrink-0 text-xs text-muted-foreground">
                                        Destination
                                    </span>
                                    <span
                                        className="min-w-0 truncate text-sm font-semibold"
                                        title={destinationLabel ?? undefined}
                                    >
                                        {destinationLabel}
                                    </span>
                                </div>
                                <div className="flex w-full flex-wrap items-center gap-x-3 gap-y-1.5 text-xs text-muted-foreground sm:w-auto">
                                    <Badge variant="outline">
                                        /{qrCode.code}
                                    </Badge>
                                    <span className="hidden sm:inline">
                                        {qrCode.source === 'linked'
                                            ? 'Linked short link'
                                            : 'Direct campaign'}
                                    </span>
                                    <Badge
                                        variant={qrCampaignBadgeVariant(state)}
                                    >
                                        {state}
                                    </Badge>
                                    {destinationUrl ? (
                                        <a
                                            href={destinationUrl}
                                            target="_blank"
                                            rel="noreferrer"
                                            className="ml-auto inline-flex w-fit items-center gap-1 text-sm font-medium text-primary underline-offset-4 hover:underline"
                                        >
                                            Open destination
                                            <ExternalLink
                                                aria-hidden="true"
                                                className="size-3.5"
                                            />
                                        </a>
                                    ) : (
                                        <span>No available destination</span>
                                    )}
                                </div>
                            </div>
                        </CardHeader>
                    </Card>
                )}

                <QrScanInsights analytics={analytics} windowUrl={windowUrl} />
            </section>
        </>
    );
}

QrCodeInsights.layout = {
    breadcrumbs: [
        { title: 'QR campaigns', href: index() },
        { title: 'QR scan insights', href: insights() },
    ],
};
