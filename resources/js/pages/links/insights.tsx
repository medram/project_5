import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, ExternalLink, Pencil } from 'lucide-react';
import { useState } from 'react';
import { AnalyticsDashboard } from '@/components/analytics/analytics-dashboard';
import { ContentEditorRoutesProvider } from '@/components/admin/content-editor-routes';
import { LinkFormDialog } from '@/components/links/link-form-dialog';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardHeader } from '@/components/ui/card';
import { linkStatus } from '@/lib/link-status';
import { edit as adminEdit } from '@/routes/admin/content/links';
import { publicMethod as publicLinkPage } from '@/routes/link-pages';
import { index, insights, update } from '@/routes/links';
import type { AnalyticsWindowDays } from '@/types/analytics';
import { type LinkInsightPageProps, toLinkListItem } from '@/types/links';

export default function LinkInsights({
    link: linkPayload,
    analytics,
    backUrl,
    backLabel,
    edit,
    windowBaseUrl,
}: LinkInsightPageProps) {
    const link = toLinkListItem(linkPayload);
    const [editOpen, setEditOpen] = useState(false);

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
    const status = linkStatus(link);
    const shortLinkLabel = link.customDomain
        ? `${link.customDomain.hostname}/${link.slug}`
        : `/${link.slug}`;
    const deliveryLabel = {
        direct: 'Direct redirect',
        splash: 'LinkPilot splash',
        link_page: 'Link page',
        overlay: 'CTA overlay',
    }[link.deliveryMode];

    function windowUrl(days: AnalyticsWindowDays): string {
        if (!windowBaseUrl) {
            return insights(link.id, { query: { days } }).url;
        }

        const separator = windowBaseUrl.includes('?') ? '&' : '?';

        return `${windowBaseUrl}${separator}days=${days}`;
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
                        <Link href={backUrl ?? index().url}>
                            <ArrowLeft data-icon="inline-start" />
                            {backLabel ?? 'Short links'}
                        </Link>
                    </Button>
                    <PageHeader
                        title={`/${link.slug}`}
                        description="Aggregate click history and consented analytics for this short link in the selected UTC window."
                        actions={
                            edit ? (
                                <Button
                                    size="sm"
                                    variant="outline"
                                    onClick={() => setEditOpen(true)}
                                >
                                    <Pencil data-icon="inline-start" />
                                    Edit link
                                </Button>
                            ) : undefined
                        }
                    />
                </section>

                <Card className="py-3">
                    <CardHeader>
                        <div className="flex min-w-0 flex-wrap items-center gap-x-3 gap-y-2">
                            <div className="flex min-w-0 flex-1 items-baseline gap-2">
                                <span className="shrink-0 text-xs text-muted-foreground">
                                    Destination
                                </span>
                                <span
                                    className="min-w-0 truncate text-sm font-semibold"
                                    title={destinationLabel}
                                >
                                    {destinationLabel}
                                </span>
                            </div>
                            <div className="flex w-full flex-wrap items-center gap-x-3 gap-y-1.5 text-xs text-muted-foreground sm:w-auto">
                                <Badge variant="outline">
                                    {shortLinkLabel}
                                </Badge>
                                <span className="hidden sm:inline">
                                    {deliveryLabel}
                                </span>
                                <Badge variant={status.variant}>
                                    {status.label}
                                </Badge>
                                {destinationUrl ? (
                                    <a
                                        href={destinationUrl}
                                        target="_blank"
                                        rel="noreferrer"
                                        className="ml-auto inline-flex w-fit items-center gap-1 text-sm font-medium text-primary underline-offset-4 hover:underline"
                                    >
                                        {link.deliveryMode === 'link_page'
                                            ? 'Open published link page'
                                            : 'Open destination'}
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

                <AnalyticsDashboard
                    mode="scoped"
                    analytics={analytics}
                    windowUrl={windowUrl}
                />
            </section>

            {edit && (
                <ContentEditorRoutesProvider
                    ownerPrefix={update(link.id).url}
                    adminPrefix={adminEdit(link.id).url}
                    backUrl={backUrl ?? index().url}
                >
                    <LinkFormDialog
                        link={link}
                        folders={edit.folders}
                        customDomains={edit.customDomains}
                        linkOverlays={edit.linkOverlays}
                        linkPages={edit.linkPages}
                        tagOptions={edit.tagOptions}
                        countries={edit.countries}
                        canManageApprovals={edit.canManageApprovals}
                        listQuery={edit.listQuery}
                        open={editOpen}
                        onOpenChange={setEditOpen}
                    />
                </ContentEditorRoutesProvider>
            )}
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
