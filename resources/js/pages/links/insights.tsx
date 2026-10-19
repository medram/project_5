import { Head, Link } from '@inertiajs/react';
import {
    ArrowLeft,
    BarChart3,
    ExternalLink,
    MousePointerClick,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { index } from '@/routes/links';
import {
    type DailyClick,
    type LinkInsightPageProps,
    toLinkListItem,
} from '@/types/links';

function formatDay(date: string): string {
    return new Intl.DateTimeFormat(undefined, {
        month: 'short',
        day: 'numeric',
    }).format(new Date(`${date}T12:00:00`));
}

function ClickSeries({ dailyClicks }: { dailyClicks: DailyClick[] }) {
    const maximum = Math.max(...dailyClicks.map(({ clicks }) => clicks), 0);

    if (maximum === 0) {
        return (
            <Empty className="bg-muted/30">
                <EmptyHeader>
                    <EmptyMedia variant="icon">
                        <BarChart3 aria-hidden="true" />
                    </EmptyMedia>
                    <EmptyTitle>No clicks in the last 14 days</EmptyTitle>
                    <EmptyDescription>
                        New visits will appear here once this route is used.
                    </EmptyDescription>
                </EmptyHeader>
            </Empty>
        );
    }

    return (
        <ol className="flex flex-col gap-3">
            {dailyClicks.map((day) => {
                const width = `${(day.clicks / maximum) * 100}%`;

                return (
                    <li
                        key={day.date}
                        className="grid grid-cols-[4.5rem_minmax(0,1fr)_auto] items-center gap-3"
                    >
                        <time
                            dateTime={day.date}
                            className="text-sm text-muted-foreground"
                        >
                            {formatDay(day.date)}
                        </time>
                        <div
                            aria-hidden="true"
                            className="h-2 overflow-hidden rounded-full bg-muted"
                        >
                            <div
                                className="h-full rounded-full bg-primary"
                                style={{ width }}
                            />
                        </div>
                        <span className="min-w-16 text-right text-sm font-medium tabular-nums">
                            {day.clicks} {day.clicks === 1 ? 'click' : 'clicks'}
                        </span>
                    </li>
                );
            })}
        </ol>
    );
}

export default function LinkInsights({
    link: linkPayload,
    totalClicks,
    dailyClicks,
}: LinkInsightPageProps) {
    const link = toLinkListItem({
        ...linkPayload,
        clicks_count: totalClicks,
    });

    return (
        <>
            <Head title={`Insights for /${link.slug}`} />

            <main className="flex flex-1 flex-col gap-6 p-4 md:p-6">
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
                    <div className="flex flex-col gap-2">
                        <div className="flex flex-wrap items-center gap-2 text-linkpilot-creator">
                            <BarChart3 aria-hidden="true" />
                            <span className="text-sm font-medium">
                                Route activity
                            </span>
                        </div>
                        <h1 className="text-3xl font-semibold tracking-tight">
                            /{link.slug}
                        </h1>
                        <p className="max-w-2xl text-sm leading-6 text-muted-foreground">
                            Visits recorded for this short link. The daily view
                            covers the current date and the preceding 13 days.
                        </p>
                    </div>
                </section>

                <section className="grid gap-4 lg:grid-cols-[minmax(0,0.7fr)_minmax(0,1.3fr)]">
                    <Card>
                        <CardHeader>
                            <CardTitle>Total clicks</CardTitle>
                            <CardDescription>
                                All recorded visits for this route.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="flex items-center gap-3">
                                <MousePointerClick
                                    aria-hidden="true"
                                    className="text-linkpilot-action"
                                />
                                <p className="text-4xl font-semibold tracking-tight tabular-nums">
                                    {totalClicks.toLocaleString()}
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Destination</CardTitle>
                            <CardDescription className="truncate">
                                {link.destinationUrl}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <a
                                href={link.destinationUrl}
                                target="_blank"
                                rel="noreferrer"
                                className="flex w-fit items-center gap-1 text-sm font-medium text-primary underline-offset-4 hover:underline"
                            >
                                Open destination
                                <ExternalLink aria-hidden="true" />
                            </a>
                        </CardContent>
                    </Card>
                </section>

                <Card>
                    <CardHeader>
                        <CardTitle>Daily clicks</CardTitle>
                        <CardDescription>
                            Each row includes its date and count; bar length is
                            only a visual comparison.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ClickSeries dailyClicks={dailyClicks} />
                    </CardContent>
                </Card>
            </main>
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
