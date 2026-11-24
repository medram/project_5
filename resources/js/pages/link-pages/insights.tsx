import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, ExternalLink } from 'lucide-react';
import { useState } from 'react';
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
import { Field, FieldLabel } from '@/components/ui/field';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type {
    LinkPageInsightVariant,
    LinkPageInsightsProps,
} from '@/types/link-pages';

function VariantStatus({ variant }: { variant: LinkPageInsightVariant }) {
    const label = variant.isControl
        ? 'Control'
        : variant.isDeleted
          ? 'Deleted'
          : variant.isActive
            ? 'Active'
            : 'Inactive';

    return (
        <Badge
            variant={
                variant.isControl || variant.isActive ? 'default' : 'secondary'
            }
        >
            {label}
        </Badge>
    );
}

export default function LinkPageInsights({
    linkPage,
    backUrl,
    insights,
}: LinkPageInsightsProps) {
    const [selectedVariantId, setSelectedVariantId] = useState<number | null>(
        () =>
            insights.variants.find((variant) => variant.isControl)?.id ??
            insights.variants[0]?.id ??
            null,
    );
    const selectedVariant =
        insights.variants.find((variant) => variant.id === selectedVariantId) ??
        insights.variants[0] ??
        null;

    function changePeriod(value: string) {
        const days = Number(value);
        if (days !== 7 && days !== 30 && days !== 90) return;

        router.get(
            window.location.pathname,
            { days },
            { preserveScroll: true, preserveState: true, replace: true },
        );
    }

    return (
        <>
            <Head title={`Insights for ${linkPage.name}`} />

            <main className="mx-auto flex w-full max-w-7xl min-w-0 flex-col gap-5 px-5 pt-4 pb-10">
                <Button asChild size="sm" variant="ghost" className="-ml-3">
                    <Link href={backUrl}>
                        <ArrowLeft data-icon="inline-start" />
                        Edit page
                    </Link>
                </Button>
                <PageHeader
                    title={`${linkPage.name} insights`}
                    description="Compare consented views, destination clicks, and submitted leads. Visitor identities are not shown."
                    actions={
                        <div className="flex flex-wrap items-end gap-2">
                            <Field className="gap-1">
                                <FieldLabel htmlFor="insights-period">
                                    Date range
                                </FieldLabel>
                                <Select
                                    value={String(insights.days)}
                                    onValueChange={changePeriod}
                                >
                                    <SelectTrigger
                                        id="insights-period"
                                        className="h-11 min-w-36"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="7">
                                            Last 7 days
                                        </SelectItem>
                                        <SelectItem value="30">
                                            Last 30 days
                                        </SelectItem>
                                        <SelectItem value="90">
                                            Last 90 days
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </Field>
                            <Button asChild variant="outline">
                                <a
                                    href={linkPage.publicUrl}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    <ExternalLink data-icon="inline-start" />
                                    Open public page
                                </a>
                            </Button>
                        </div>
                    }
                />

                <Card>
                    <CardHeader>
                        <CardTitle>Variant comparison</CardTitle>
                        <CardDescription>
                            {insights.startDate} to {insights.endDate}. Only
                            consented activity is included.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {insights.variants.length === 0 ? (
                            <p className="rounded-lg border border-dashed p-4 text-sm text-muted-foreground">
                                No variants are available for this page.
                            </p>
                        ) : (
                            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                                {insights.variants.map((variant) => (
                                    <Card
                                        key={variant.id}
                                        className="shadow-none"
                                    >
                                        <CardHeader className="gap-2 pb-3">
                                            <div className="flex flex-wrap items-center justify-between gap-2">
                                                <CardTitle className="text-base">
                                                    {variant.name}
                                                </CardTitle>
                                                <VariantStatus
                                                    variant={variant}
                                                />
                                            </div>
                                            <CardDescription>
                                                {variant.weight}% visitor share
                                            </CardDescription>
                                        </CardHeader>
                                        <CardContent>
                                            <dl className="grid grid-cols-3 gap-3 text-sm">
                                                <div>
                                                    <dt className="text-muted-foreground">
                                                        Views
                                                    </dt>
                                                    <dd className="text-lg font-semibold">
                                                        {variant.views}
                                                    </dd>
                                                </div>
                                                <div>
                                                    <dt className="text-muted-foreground">
                                                        Clicks
                                                    </dt>
                                                    <dd className="text-lg font-semibold">
                                                        {variant.clicks}
                                                    </dd>
                                                </div>
                                                <div>
                                                    <dt className="text-muted-foreground">
                                                        Leads
                                                    </dt>
                                                    <dd className="text-lg font-semibold">
                                                        {variant.leads}
                                                    </dd>
                                                </div>
                                            </dl>
                                        </CardContent>
                                    </Card>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Daily activity</CardTitle>
                        <CardDescription>
                            {selectedVariant
                                ? `Daily totals for ${selectedVariant.name}.`
                                : 'No variant activity is available.'}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {selectedVariant && (
                            <Field className="gap-1 sm:max-w-sm">
                                <FieldLabel htmlFor="daily-variant">
                                    Variant
                                </FieldLabel>
                                <Select
                                    value={String(selectedVariant.id)}
                                    onValueChange={(value) =>
                                        setSelectedVariantId(Number(value))
                                    }
                                >
                                    <SelectTrigger
                                        id="daily-variant"
                                        className="h-11 w-full"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {insights.variants.map((variant) => (
                                            <SelectItem
                                                key={variant.id}
                                                value={String(variant.id)}
                                            >
                                                {variant.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </Field>
                        )}
                        {!selectedVariant ||
                        selectedVariant.daily.every(
                            (day) =>
                                day.views === 0 &&
                                day.clicks === 0 &&
                                day.leads === 0,
                        ) ? (
                            <p className="rounded-lg border border-dashed p-4 text-sm text-muted-foreground">
                                No consented activity for this variant in this
                                date range.
                            </p>
                        ) : (
                            <ul className="divide-y rounded-lg border">
                                {selectedVariant.daily.map((day) => (
                                    <li
                                        key={day.date}
                                        className="grid grid-cols-2 gap-3 p-3 sm:grid-cols-4"
                                    >
                                        <div>
                                            <p className="text-xs text-muted-foreground">
                                                Date
                                            </p>
                                            <p className="font-medium">
                                                {day.date}
                                            </p>
                                        </div>
                                        <div>
                                            <p className="text-xs text-muted-foreground">
                                                Views
                                            </p>
                                            <p>{day.views}</p>
                                        </div>
                                        <div>
                                            <p className="text-xs text-muted-foreground">
                                                Clicks
                                            </p>
                                            <p>{day.clicks}</p>
                                        </div>
                                        <div>
                                            <p className="text-xs text-muted-foreground">
                                                Leads
                                            </p>
                                            <p>{day.leads}</p>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>
            </main>
        </>
    );
}
