import { Head, Link } from '@inertiajs/react';
import { Activity, ArrowLeft, QrCode } from 'lucide-react';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { campaignInsights, index, insights } from '@/routes/qr-codes';
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

    return (
        <>
            <Head title={`${title} · QR insights`} />
            <section className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="QR scan insights"
                    eyebrow={
                        scope === 'campaign'
                            ? 'Campaign analytics'
                            : 'Workspace analytics'
                    }
                    icon={Activity}
                    description="Counts come only from anonymous QR scan records; short-link clicks and consent-based analytics are not included."
                    actions={
                        <Button variant="outline" asChild>
                            <Link href={index().url}>
                                <ArrowLeft data-icon="inline-start" />
                                QR campaigns
                            </Link>
                        </Button>
                    }
                />

                {qrCode && (
                    <Card>
                        <CardHeader>
                            <CardDescription>Campaign</CardDescription>
                            <CardTitle className="[overflow-wrap:anywhere] break-words">
                                {title}
                            </CardTitle>
                            <div className="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                                <Badge variant="outline">/{qrCode.code}</Badge>
                                <span className="min-w-0 break-all">
                                    {qrCode.link
                                        ? `/${qrCode.link.slug}`
                                        : qrCode.destination_url}
                                </span>
                            </div>
                        </CardHeader>
                    </Card>
                )}

                <div className="grid gap-4 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end">
                    <Card>
                        <CardHeader className="pb-2">
                            <CardDescription>
                                Recorded scans · last {analytics.days} days
                            </CardDescription>
                            <CardTitle className="text-3xl tabular-nums">
                                {analytics.totalScans}
                            </CardTitle>
                        </CardHeader>
                    </Card>
                    <nav
                        aria-label="Analytics time range"
                        className="flex flex-wrap gap-2"
                    >
                        {[7, 30, 90].map((days) => (
                            <Button
                                key={days}
                                variant={
                                    analytics.days === days
                                        ? 'default'
                                        : 'outline'
                                }
                                size="sm"
                                asChild
                            >
                                <Link href={`${currentUrl}?days=${days}`}>
                                    {days} days
                                </Link>
                            </Button>
                        ))}
                    </nav>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <QrCode aria-hidden="true" className="size-5" />
                            Daily scans
                        </CardTitle>
                        <CardDescription>
                            Each row counts one successful visit to a valid QR
                            campaign URL.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="max-h-[32rem] overflow-auto rounded-md border">
                            <table className="w-full text-sm">
                                <caption className="sr-only">
                                    Daily QR scan counts for the selected period
                                </caption>
                                <thead className="sticky top-0 bg-muted text-left">
                                    <tr>
                                        <th
                                            scope="col"
                                            className="px-4 py-3 font-medium"
                                        >
                                            Date
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-4 py-3 text-right font-medium"
                                        >
                                            Scans
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {analytics.daily.map((row) => (
                                        <tr key={row.date} className="border-t">
                                            <th
                                                scope="row"
                                                className="px-4 py-3 text-left font-normal"
                                            >
                                                {new Date(
                                                    `${row.date}T12:00:00Z`,
                                                ).toLocaleDateString(
                                                    undefined,
                                                    {
                                                        year: 'numeric',
                                                        month: 'short',
                                                        day: 'numeric',
                                                    },
                                                )}
                                            </th>
                                            <td className="px-4 py-3 text-right tabular-nums">
                                                {row.scans}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
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
