import { Head, Link } from '@inertiajs/react';
import { SlidersHorizontal } from 'lucide-react';
import { AnalyticsDashboard } from '@/components/analytics/analytics-dashboard';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { exportMethod, events, index } from '@/routes/analytics';
import { index as customEventsIndex } from '@/routes/analytics/custom-events';
import type { AccountAnalyticsProps } from '@/types/analytics';

export default function AccountAnalytics({
    analytics,
    filters,
    canManageCustomEvents,
}: AccountAnalyticsProps) {
    return (
        <>
            <Head title="Insights" />

            <section className="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-5 px-5 pt-4 pb-10">
                <PageHeader
                    title="Insights"
                    description="Every successful delivery is counted. Visitors, events, and dimensions appear only after consent."
                    actions={
                        canManageCustomEvents ? (
                            <Button asChild variant="outline">
                                <Link href={customEventsIndex().url}>
                                    <SlidersHorizontal data-icon="inline-start" />
                                    Manage custom events
                                </Link>
                            </Button>
                        ) : undefined
                    }
                />

                <AnalyticsDashboard
                    mode="account"
                    analytics={analytics}
                    filters={filters}
                    filterUrl={index().url}
                    eventsUrl={events({ query: filters }).url}
                    exportUrl={exportMethod({ query: filters }).url}
                />

                <p className="lp-panel max-w-3xl p-4 text-xs leading-5 text-muted-foreground">
                    Consented events keep a keyed visitor hash and coarse device
                    dimensions. Raw IP addresses, full user agents, destination
                    URLs, and access codes are never stored.
                </p>
            </section>
        </>
    );
}

AccountAnalytics.layout = {
    breadcrumbs: [
        {
            title: 'Insights',
            href: index(),
        },
    ],
};
