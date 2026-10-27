import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, ListFilter } from 'lucide-react';
import { AnalyticsFilterForm } from '@/components/analytics/analytics-dashboard';
import { PageHeader } from '@/components/page-header';
import { Card, CardContent } from '@/components/ui/card';
import { events as eventsRoute, exportMethod, index } from '@/routes/analytics';
import type {
    AnalyticsEventPageProps,
    AnalyticsEventRecord,
} from '@/types/analytics';

function formatOccurredAt(value: string): string {
    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone: 'UTC',
    }).format(new Date(value));
}

function resources(event: AnalyticsEventRecord): string[] {
    return [
        ['Link', event.link_id],
        ['Bio page', event.bio_page_id],
        ['Bio link', event.bio_link_id],
        ['Link page', event.link_page_id],
        ['Link-page variant', event.link_page_variant_id],
        ['Link-page item', event.link_page_item_id],
        ['Overlay', event.link_overlay_id],
    ]
        .filter(([, id]) => id !== null)
        .map(([label, id]) => `${label} #${id}`);
}

function paginationLabel(label: string): string {
    return label.replaceAll('&laquo;', '‹').replaceAll('&raquo;', '›');
}

export default function AnalyticsEvents({
    events,
    filters,
}: AnalyticsEventPageProps) {
    return (
        <>
            <Head title="Analytics events" />

            <section className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Analytics events"
                    eyebrow="Privacy-first insights"
                    icon={ListFilter}
                    description="Consented event records for the selected window. Visitor hashes and raw IP addresses are not included."
                    actions={
                        <div className="flex flex-wrap items-center gap-2">
                            <Link
                                href={index({ query: filters }).url}
                                className="inline-flex min-h-10 items-center gap-2 rounded-md border px-3 text-sm font-medium hover:bg-muted"
                            >
                                <ArrowLeft
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                Overview
                            </Link>
                        </div>
                    }
                />
                <AnalyticsFilterForm
                    filters={filters}
                    actionUrl={eventsRoute().url}
                    exportUrl={exportMethod({ query: filters }).url}
                />

                <Card>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[900px] text-left text-sm">
                                <thead className="bg-muted/50 text-muted-foreground">
                                    <tr>
                                        <th
                                            scope="col"
                                            className="px-4 py-3 font-medium"
                                        >
                                            Occurred (UTC)
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-4 py-3 font-medium"
                                        >
                                            Event
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-4 py-3 font-medium"
                                        >
                                            Resource references
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-4 py-3 font-medium"
                                        >
                                            Country
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-4 py-3 font-medium"
                                        >
                                            Browser / platform
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-4 py-3 font-medium"
                                        >
                                            UTM campaign
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-4 py-3 font-medium"
                                        >
                                            Referrer
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {events.data.length === 0 ? (
                                        <tr>
                                            <td
                                                colSpan={7}
                                                className="px-4 py-12 text-center text-muted-foreground"
                                            >
                                                No events match these filters.
                                            </td>
                                        </tr>
                                    ) : (
                                        events.data.map((event) => (
                                            <tr
                                                key={event.uuid}
                                                className="border-t"
                                            >
                                                <td className="px-4 py-3 whitespace-nowrap">
                                                    <time
                                                        dateTime={
                                                            event.occurred_at
                                                        }
                                                    >
                                                        {formatOccurredAt(
                                                            event.occurred_at,
                                                        )}
                                                    </time>
                                                </td>
                                                <td className="px-4 py-3 font-medium">
                                                    {event.event_name}
                                                </td>
                                                <td className="px-4 py-3">
                                                    {resources(event).join(
                                                        ', ',
                                                    ) || '—'}
                                                </td>
                                                <td className="px-4 py-3">
                                                    {event.country_code ?? '—'}
                                                </td>
                                                <td className="px-4 py-3">
                                                    {[
                                                        event.browser,
                                                        event.platform,
                                                    ]
                                                        .filter(Boolean)
                                                        .join(' / ') || '—'}
                                                </td>
                                                <td className="px-4 py-3">
                                                    {event.utm_campaign ?? '—'}
                                                </td>
                                                <td className="max-w-56 truncate px-4 py-3">
                                                    {event.referrer_host ?? '—'}
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>

                <nav
                    aria-label="Analytics event pages"
                    className="flex flex-wrap gap-2"
                >
                    {events.links.map((page) =>
                        page.url ? (
                            <Link
                                key={`${page.label}-${page.url}`}
                                href={page.url}
                                preserveScroll
                                aria-current={page.active ? 'page' : undefined}
                                className={`inline-flex min-h-9 items-center rounded-md border px-3 text-sm ${page.active ? 'bg-primary text-primary-foreground' : 'hover:bg-muted'}`}
                            >
                                {paginationLabel(page.label)}
                            </Link>
                        ) : (
                            <span
                                key={page.label}
                                aria-disabled="true"
                                className="inline-flex min-h-9 items-center rounded-md border px-3 text-sm text-muted-foreground"
                            >
                                {paginationLabel(page.label)}
                            </span>
                        ),
                    )}
                </nav>
            </section>
        </>
    );
}
