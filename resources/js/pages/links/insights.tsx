import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, ExternalLink } from 'lucide-react';
import { AnalyticsDashboard } from '@/components/analytics/analytics-dashboard';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { publicMethod as publicLinkPage } from '@/routes/link-pages';
import { index, insights } from '@/routes/links';
import type { AnalyticsWindowDays } from '@/types/analytics';
import { type LinkInsightPageProps, toLinkListItem } from '@/types/links';

export default function LinkInsights({
    link: linkPayload,
    analytics,
}: LinkInsightPageProps) {
    const link = toLinkListItem(linkPayload);

    const destinationUrl =
        link.deliveryMode === 'link_page'
            ? link.linkPage
                ? publicLinkPage(link.linkPage.slug).url
                : null
            : link.destinationUrl;
    const destinationLabel =
        link.deliveryMode === 'link_page'
            ? (link.linkPage?.name ?? 'Unavailable link page')
            : (link.destinationUrl ?? 'No destination');

    function windowUrl(days: AnalyticsWindowDays): string {
        return insights(link.id, { query: { days } }).url;
    }

    return (
        <>
            <Head title={`Insights for /${link.slug}`} />

            <section className="mx-auto flex w-full max-w-7xl min-w-0 flex-1 flex-col gap-5 px-5 pt-4 pb-10">
                <section className="flex flex-col gap-4">
                    <Button
                        variant="outline"
                        size="sm"
                        className="w-fit"
                        asChild
                    >
                        <Link href={index()}>
                            <ArrowLeft data-icon="inline-start" />
                            Short links
                        </Link>
                    </Button>
                    <PageHeader
                        title={`/${link.slug}`}
                        description="Aggregate click history and consented analytics for this short link in the selected UTC window."
                    />
                </section>

                <Card>
                    <CardHeader>
                        <CardTitle>Destination</CardTitle>
                        <CardDescription className="truncate">
                            {destinationLabel}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {destinationUrl ? (
                            <a
                                href={destinationUrl}
                                target="_blank"
                                rel="noreferrer"
                                className="flex w-fit items-center gap-1 text-sm font-medium text-primary underline-offset-4 hover:underline"
                            >
                                {link.deliveryMode === 'link_page'
                                    ? 'Open published link page'
                                    : 'Open destination'}
                                <ExternalLink aria-hidden="true" />
                            </a>
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                No available destination
                            </p>
                        )}
                    </CardContent>
                </Card>

                <AnalyticsDashboard
                    mode="scoped"
                    analytics={analytics}
                    windowUrl={windowUrl}
                />
            </section>
        </>
    );
}

LinkInsights.layout = {
    breadcrumbs: [
        {
            title: 'Short links',
            href: index(),
        },
        {
            title: 'Insights',
            href: index(),
        },
    ],
};
