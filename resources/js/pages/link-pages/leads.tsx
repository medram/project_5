import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Download } from 'lucide-react';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import type { LinkPageLeadsProps } from '@/types/link-pages';

function submittedAtLabel(value: string): string {
    const date = new Date(value);
    if (Number.isNaN(date.valueOf())) return value;

    return `${new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone: 'UTC',
    }).format(date)} UTC`;
}

export default function LinkPageLeads({
    linkPage,
    backUrl,
    exportUrl,
    columns,
    leads,
}: LinkPageLeadsProps) {
    return (
        <>
            <Head title={`Lead responses for ${linkPage.name}`} />

            <main className="mx-auto flex w-full max-w-7xl min-w-0 flex-col gap-5 px-5 pt-4 pb-10">
                <Button asChild size="sm" variant="ghost" className="-ml-3">
                    <Link href={backUrl}>
                        <ArrowLeft data-icon="inline-start" />
                        Link page
                    </Link>
                </Button>
                <PageHeader
                    title="Lead responses"
                    description={`Responses for ${linkPage.name}. Submissions are encrypted, visible only to workspace owners and administrators, and kept for up to 30 days.`}
                    actions={
                        <Button asChild variant="outline">
                            <a href={exportUrl}>
                                <Download data-icon="inline-start" />
                                Download CSV
                            </a>
                        </Button>
                    }
                />

                {leads.data.length === 0 ? (
                    <Card variant="transparent">
                        <CardContent className="py-10 text-center">
                            <h2 className="font-medium">No responses yet</h2>
                            <p className="mt-2 text-sm text-muted-foreground">
                                Publish this page and share it to start
                                collecting consented responses.
                            </p>
                        </CardContent>
                    </Card>
                ) : (
                    <section aria-label="Lead responses" className="grid gap-3">
                        {leads.data.map((lead) => {
                            const responseColumns = columns.filter(
                                (column) =>
                                    column.formBlockId === lead.formBlockId,
                            );

                            return (
                                <article
                                    key={lead.id}
                                    className="min-w-0 rounded-lg border bg-card p-4"
                                >
                                    <header className="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 border-b pb-3">
                                        <time
                                            dateTime={lead.submittedAt}
                                            className="text-sm font-medium"
                                        >
                                            {submittedAtLabel(lead.submittedAt)}
                                        </time>
                                        <span className="text-sm text-muted-foreground">
                                            Form {lead.formBlockId}
                                        </span>
                                    </header>
                                    <dl className="grid gap-3 pt-3 sm:grid-cols-2">
                                        {responseColumns.map((column) => (
                                            <div
                                                key={column.key}
                                                className="min-w-0"
                                            >
                                                <dt className="text-sm text-muted-foreground">
                                                    {column.label}
                                                </dt>
                                                <dd className="mt-1 text-sm break-words whitespace-pre-wrap">
                                                    {lead.values[
                                                        column.fieldId
                                                    ] ?? '—'}
                                                </dd>
                                            </div>
                                        ))}
                                    </dl>
                                </article>
                            );
                        })}
                    </section>
                )}

                <nav
                    aria-label="Lead response pages"
                    className="flex flex-wrap items-center justify-between gap-3"
                >
                    <p
                        aria-live="polite"
                        className="text-sm text-muted-foreground"
                    >
                        Page {leads.current_page} of {leads.last_page}
                    </p>
                    <div className="flex gap-2">
                        {leads.prev_page_url ? (
                            <Button asChild size="sm" variant="outline">
                                <Link href={leads.prev_page_url}>Previous</Link>
                            </Button>
                        ) : (
                            <Button size="sm" variant="outline" disabled>
                                Previous
                            </Button>
                        )}
                        {leads.next_page_url ? (
                            <Button asChild size="sm" variant="outline">
                                <Link href={leads.next_page_url}>Next</Link>
                            </Button>
                        ) : (
                            <Button size="sm" variant="outline" disabled>
                                Next
                            </Button>
                        )}
                    </div>
                </nav>
            </main>
        </>
    );
}
