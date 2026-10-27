import { Head, Link } from '@inertiajs/react';
import { BarChart3, ShieldCheck } from 'lucide-react';
import { AnalyticsDashboard } from '@/components/analytics/analytics-dashboard';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
            <Head title="Analytics" />

            <section className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Analytics"
                    eyebrow="Privacy-first insights"
                    icon={BarChart3}
                    description="Aggregate clicks are counted for every successful short-link delivery. Unique visitors, tracked events, and dimensions appear only after consent."
                    actions={
                        canManageCustomEvents ? (
                            <Button asChild variant="outline">
                                <Link href={customEventsIndex().url}>
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

                <Card className="max-w-3xl">
                    <CardHeader>
                        <div className="flex items-center gap-2">
                            <ShieldCheck
                                className="size-5 text-primary"
                                aria-hidden="true"
                            />
                            <CardTitle>Privacy boundary</CardTitle>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <p className="text-sm leading-6 text-muted-foreground">
                            Consented events retain a keyed visitor hash and
                            coarse device dimensions, never a raw IP address,
                            full user agent, destination URL, or access code.
                        </p>
                    </CardContent>
                </Card>
            </section>
        </>
    );
}

AccountAnalytics.layout = {
    breadcrumbs: [
        {
            title: 'Analytics',
            href: index(),
        },
    ],
};
