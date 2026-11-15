import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Check, CircleAlert } from 'lucide-react';
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

interface ImportResult {
    row: number;
    status: 'created' | 'error';
    slug?: string;
    errors: string[];
}

interface ImportResultsProps {
    results: ImportResult[];
    listState: Record<string, string | number>;
    summary: {
        created: number;
        failed: number;
    };
}

export default function LinkImportResults({
    results,
    listState,
    summary,
}: ImportResultsProps) {
    const query = new URLSearchParams();
    Object.entries(listState).forEach(([key, value]) =>
        query.set(key, String(value)),
    );
    const backUrl =
        query.size > 0 ? `/dashboard/links?${query}` : '/dashboard/links';
    return (
        <>
            <Head title="CSV import results" />
            <section className="mx-auto flex w-full max-w-7xl min-w-0 flex-1 flex-col gap-5 px-5 pt-4 pb-10">
                <PageHeader
                    title="CSV import results"
                    description={`${summary.created} created · ${summary.failed} failed`}
                    actions={
                        <Button variant="outline" asChild className="min-h-11">
                            <Link href={backUrl}>
                                <ArrowLeft data-icon="inline-start" />
                                Back to short links
                            </Link>
                        </Button>
                    }
                />
                <section
                    aria-label="CSV row results"
                    className="grid min-w-0 gap-3"
                >
                    {results.map((result) => (
                        <Card key={result.row}>
                            <CardHeader className="flex flex-row flex-wrap items-start justify-between gap-3">
                                <div className="min-w-0">
                                    <CardTitle className="text-base">
                                        CSV row {result.row}
                                    </CardTitle>
                                    <CardDescription>
                                        {result.status === 'created'
                                            ? `Created /${result.slug}`
                                            : 'This row was not imported.'}
                                    </CardDescription>
                                </div>
                                <Badge
                                    variant={
                                        result.status === 'created'
                                            ? 'secondary'
                                            : 'destructive'
                                    }
                                >
                                    {result.status === 'created' ? (
                                        <Check
                                            data-icon="inline-start"
                                            aria-hidden="true"
                                        />
                                    ) : (
                                        <CircleAlert
                                            data-icon="inline-start"
                                            aria-hidden="true"
                                        />
                                    )}
                                    {result.status === 'created'
                                        ? 'Created'
                                        : 'Failed'}
                                </Badge>
                            </CardHeader>
                            {result.errors.length > 0 && (
                                <CardContent>
                                    <ul className="list-disc pl-5 text-sm text-destructive">
                                        {result.errors.map((error) => (
                                            <li key={error}>{error}</li>
                                        ))}
                                    </ul>
                                </CardContent>
                            )}
                        </Card>
                    ))}
                </section>
            </section>
        </>
    );
}
