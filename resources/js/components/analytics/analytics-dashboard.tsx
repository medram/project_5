import { Link, router } from '@inertiajs/react';
import { type FormEvent, useEffect, useState } from 'react';
import { ArrowUpRight, ChevronDown, SlidersHorizontal } from 'lucide-react';
import {
    DailyLineChart,
    formatChartDay,
} from '@/components/analytics/daily-line-chart';
import { TrafficSourcesSection } from '@/components/analytics/traffic-sources';
import {
    AnalyticsWindowPicker,
    analyticsDateWindows,
} from '@/components/analytics/window-picker';
import { StatCard } from '@/components/dashboard/stat-card';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Field,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { SegmentedControl } from '@/components/ui/segmented-control';
import type {
    AnalyticsDaily,
    AnalyticsFilters,
    AnalyticsInsightsResult,
    AnalyticsMetric,
    AnalyticsResourceType,
    AnalyticsWindowDays,
} from '@/types/analytics';

function DailyChart({
    daily,
    metric,
    showDailyValues = true,
}: {
    daily: AnalyticsDaily[];
    metric: AnalyticsMetric;
    showDailyValues?: boolean;
}) {
    const dataKey = metric === 'engagements' ? 'clicks' : 'events';
    const label = metric === 'engagements' ? 'clicks' : 'tracked events';

    return (
        <div className="grid min-w-0 gap-4">
            <DailyLineChart
                data={daily}
                dataKey={dataKey}
                label={metric === 'engagements' ? 'Clicks' : 'Tracked events'}
                ariaLabel={`Daily ${label} for the selected UTC window.`}
                emptyTitle={`No ${label} in this window`}
                emptyDescription="Choose another window or check again after visitors interact."
            />
            {showDailyValues && (
                <details className="rounded-md border">
                    <summary className="cursor-pointer px-3 py-2 text-xs text-muted-foreground">
                        Exact daily values
                    </summary>
                    <div
                        data-table-surface={
                            daily.length > 0 ? 'populated' : undefined
                        }
                        className="max-h-64 overflow-y-auto border-t"
                    >
                        <table
                            aria-label={`Daily ${label}`}
                            className="w-full text-sm"
                        >
                            <thead className="sticky top-0 bg-background text-left">
                                <tr>
                                    <th className="px-3 py-2 font-medium">
                                        UTC date
                                    </th>
                                    <th className="px-3 py-2 text-right font-medium">
                                        Count
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {daily.map((day) => (
                                    <tr key={day.date} className="border-t">
                                        <td className="px-3 py-2">
                                            <time dateTime={day.date}>
                                                {formatChartDay(day.date)}
                                            </time>
                                        </td>
                                        <td className="px-3 py-2 text-right tabular-nums">
                                            {day[dataKey]}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </details>
            )}
        </div>
    );
}

type TextFilterName = Exclude<
    keyof AnalyticsFilters,
    'days' | 'resource_type' | 'resource_id'
>;

type TextFilterDefinition = {
    name: TextFilterName;
    label: string;
    maxLength: number;
    placeholder?: string;
};

type AnalyticsFilterFormState = {
    days: string;
    resource_type: AnalyticsResourceType | 'all';
    resource_id: string;
} & Record<TextFilterName, string>;

const resourceTypes: readonly {
    value: AnalyticsResourceType;
    label: string;
}[] = [
    { value: 'link', label: 'Short link' },
    { value: 'bio_page', label: 'Bio page' },
    { value: 'bio_link', label: 'Bio link' },
    { value: 'link_page', label: 'Link page' },
    { value: 'link_page_variant', label: 'Link-page variant' },
    { value: 'link_page_item', label: 'Link-page item' },
    { value: 'link_overlay', label: 'CTA overlay' },
];

const eventFilters: readonly TextFilterDefinition[] = [
    { name: 'event_name', label: 'Event name', maxLength: 64 },
    { name: 'browser', label: 'Browser', maxLength: 32 },
    { name: 'platform', label: 'Platform', maxLength: 32 },
    { name: 'country', label: 'Country code', maxLength: 2, placeholder: 'US' },
];

const campaignFilters: readonly TextFilterDefinition[] = [
    { name: 'utm_source', label: 'UTM source', maxLength: 512 },
    { name: 'utm_medium', label: 'UTM medium', maxLength: 512 },
    { name: 'utm_campaign', label: 'UTM campaign', maxLength: 512 },
    { name: 'utm_term', label: 'UTM term', maxLength: 512 },
    { name: 'utm_content', label: 'UTM content', maxLength: 512 },
];

const emptyAnalyticsFilters: AnalyticsFilters = {
    days: 30,
    resource_type: null,
    resource_id: null,
    event_name: null,
    browser: null,
    platform: null,
    country: null,
    utm_source: null,
    utm_medium: null,
    utm_campaign: null,
    utm_term: null,
    utm_content: null,
};

function formStateFromFilters(
    filters: AnalyticsFilters,
): AnalyticsFilterFormState {
    return {
        days: String(filters.days),
        resource_type: filters.resource_type ?? 'all',
        resource_id: filters.resource_id?.toString() ?? '',
        event_name: filters.event_name ?? '',
        browser: filters.browser ?? '',
        platform: filters.platform ?? '',
        country: filters.country ?? '',
        utm_source: filters.utm_source ?? '',
        utm_medium: filters.utm_medium ?? '',
        utm_campaign: filters.utm_campaign ?? '',
        utm_term: filters.utm_term ?? '',
        utm_content: filters.utm_content ?? '',
    };
}

export function AnalyticsFilterForm({
    filters,
    actionUrl,
    exportUrl,
}: {
    filters: AnalyticsFilters;
    actionUrl: string;
    exportUrl: string;
}) {
    const [formFilters, setFormFilters] = useState(() =>
        formStateFromFilters(filters),
    );
    const [resourceError, setResourceError] = useState<string | null>(null);

    useEffect(() => {
        setFormFilters(formStateFromFilters(filters));
        setResourceError(null);
    }, [filters]);

    function updateTextFilter(name: TextFilterName, value: string) {
        setFormFilters((current) => ({ ...current, [name]: value }));
    }

    function applyFilters(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        const resourceId = formFilters.resource_id.trim();
        const hasResourceType = formFilters.resource_type !== 'all';

        if (hasResourceType !== (resourceId !== '')) {
            setResourceError(
                'Choose both a resource type and resource ID to filter a specific resource.',
            );
            return;
        }

        setResourceError(null);
        const normalized = {
            ...formFilters,
            resource_id: resourceId,
            event_name: formFilters.event_name.trim(),
            browser: formFilters.browser.trim(),
            platform: formFilters.platform.trim(),
            country: formFilters.country.trim().toUpperCase(),
            utm_source: formFilters.utm_source.trim(),
            utm_medium: formFilters.utm_medium.trim(),
            utm_campaign: formFilters.utm_campaign.trim(),
            utm_term: formFilters.utm_term.trim(),
            utm_content: formFilters.utm_content.trim(),
        };
        setFormFilters(normalized);

        const query = Object.fromEntries(
            Object.entries(normalized).filter(
                ([key, value]) =>
                    value !== '' &&
                    !(key === 'resource_type' && value === 'all'),
            ),
        );

        router.get(actionUrl, query, {
            preserveState: true,
            preserveScroll: true,
        });
    }

    function clearFilters() {
        setFormFilters(formStateFromFilters(emptyAnalyticsFilters));
        setResourceError(null);
        router.get(
            actionUrl,
            {},
            { preserveState: true, preserveScroll: true },
        );
    }

    function renderTextFilter(field: TextFilterDefinition) {
        return (
            <Field key={field.name}>
                <FieldLabel htmlFor={`analytics-${field.name}`}>
                    {field.label}
                </FieldLabel>
                <Input
                    id={`analytics-${field.name}`}
                    name={field.name}
                    value={formFilters[field.name]}
                    maxLength={field.maxLength}
                    placeholder={field.placeholder}
                    pattern={
                        field.name === 'country' ? '[A-Za-z]{2}' : undefined
                    }
                    onChange={(event) =>
                        updateTextFilter(field.name, event.currentTarget.value)
                    }
                />
            </Field>
        );
    }

    const activeCampaignFilters = campaignFilters.filter(
        (field) => formFilters[field.name] !== '',
    ).length;

    const activeFilterCount =
        (formFilters.resource_type !== 'all' ? 1 : 0) +
        (formFilters.resource_id !== '' ? 1 : 0) +
        eventFilters.filter((field) => formFilters[field.name] !== '').length +
        activeCampaignFilters;

    return (
        <details className="group rounded-2xl border border-border bg-card shadow-[var(--shadow-surface)]">
            <summary className="flex cursor-pointer list-none items-center gap-2 px-4 py-3 text-sm font-medium focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none">
                <SlidersHorizontal
                    className="size-4 text-muted-foreground"
                    aria-hidden="true"
                />
                Filters
                {activeFilterCount > 0 && (
                    <span className="text-xs font-normal text-muted-foreground tabular-nums">
                        {activeFilterCount} active
                    </span>
                )}
                <span className="ml-auto text-xs font-normal text-muted-foreground">
                    Last {formFilters.days} days
                </span>
                <ChevronDown
                    className="size-4 text-muted-foreground transition-transform group-open:rotate-180"
                    aria-hidden="true"
                />
            </summary>
            <div className="border-t p-4">
                <form onSubmit={applyFilters}>
                    <FieldGroup className="gap-4">
                        <FieldGroup className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                            <Field>
                                <FieldLabel htmlFor="analytics-days">
                                    Date range
                                </FieldLabel>
                                <Select
                                    name="days"
                                    value={formFilters.days}
                                    onValueChange={(days) =>
                                        setFormFilters((current) => ({
                                            ...current,
                                            days,
                                        }))
                                    }
                                >
                                    <SelectTrigger
                                        id="analytics-days"
                                        className="w-full"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectGroup>
                                            {analyticsDateWindows.map(
                                                (days) => (
                                                    <SelectItem
                                                        key={days}
                                                        value={String(days)}
                                                    >
                                                        Last {days} days
                                                    </SelectItem>
                                                ),
                                            )}
                                        </SelectGroup>
                                    </SelectContent>
                                </Select>
                            </Field>
                            <Field
                                data-invalid={resourceError ? true : undefined}
                            >
                                <FieldLabel htmlFor="analytics-resource-type">
                                    Resource type
                                </FieldLabel>
                                <Select
                                    name="resource_type"
                                    value={formFilters.resource_type}
                                    onValueChange={(resourceType) =>
                                        setFormFilters((current) => ({
                                            ...current,
                                            resource_type:
                                                resourceType as AnalyticsFilterFormState['resource_type'],
                                        }))
                                    }
                                >
                                    <SelectTrigger
                                        id="analytics-resource-type"
                                        className="w-full"
                                        aria-invalid={
                                            resourceError ? true : undefined
                                        }
                                        aria-describedby={
                                            resourceError
                                                ? 'analytics-resource-error'
                                                : undefined
                                        }
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectGroup>
                                            <SelectItem value="all">
                                                All resources
                                            </SelectItem>
                                            {resourceTypes.map((resource) => (
                                                <SelectItem
                                                    key={resource.value}
                                                    value={resource.value}
                                                >
                                                    {resource.label}
                                                </SelectItem>
                                            ))}
                                        </SelectGroup>
                                    </SelectContent>
                                </Select>
                            </Field>
                            <Field
                                data-invalid={resourceError ? true : undefined}
                            >
                                <FieldLabel htmlFor="analytics-resource-id">
                                    Resource ID
                                </FieldLabel>
                                <Input
                                    id="analytics-resource-id"
                                    name="resource_id"
                                    type="number"
                                    min={1}
                                    step={1}
                                    value={formFilters.resource_id}
                                    aria-invalid={
                                        resourceError ? true : undefined
                                    }
                                    aria-describedby={
                                        resourceError
                                            ? 'analytics-resource-error'
                                            : undefined
                                    }
                                    onChange={(event) => {
                                        const resourceId =
                                            event.currentTarget.value;
                                        setFormFilters((current) => ({
                                            ...current,
                                            resource_id: resourceId,
                                        }));
                                    }}
                                />
                            </Field>
                            {eventFilters.map(renderTextFilter)}
                        </FieldGroup>
                        {resourceError && (
                            <FieldError
                                id="analytics-resource-error"
                                role="alert"
                            >
                                {resourceError}
                            </FieldError>
                        )}
                        <details className="rounded-md border p-4">
                            <summary className="cursor-pointer text-sm font-medium">
                                Campaign filters
                                {activeCampaignFilters > 0 &&
                                    ` (${activeCampaignFilters} active)`}
                            </summary>
                            <FieldGroup className="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                                {campaignFilters.map(renderTextFilter)}
                            </FieldGroup>
                        </details>
                        <div className="flex flex-wrap gap-2">
                            <Button type="submit">Apply filters</Button>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={clearFilters}
                            >
                                Clear filters
                            </Button>
                            <Button asChild variant="outline">
                                <a href={exportUrl}>Export CSV</a>
                            </Button>
                        </div>
                    </FieldGroup>
                </form>
            </div>
        </details>
    );
}

type AnalyticsDashboardProps =
    | {
          mode: 'account';
          analytics: AnalyticsInsightsResult;
          filters: AnalyticsFilters;
          filterUrl: string;
          eventsUrl: string;
          exportUrl: string;
      }
    | {
          mode: 'scoped';
          analytics: AnalyticsInsightsResult;
          windowUrl: (days: AnalyticsWindowDays) => string;
      };

export function AnalyticsDashboard(props: AnalyticsDashboardProps) {
    const { analytics } = props;

    const [metric, setMetric] = useState<AnalyticsMetric>('engagements');

    useEffect(() => {
        let interval: number | undefined;
        let isReloading = false;

        const reloadAnalytics = () => {
            if (document.visibilityState !== 'visible' || isReloading) {
                return;
            }

            isReloading = true;
            router.reload({
                only: ['analytics'],
                onFinish: () => {
                    isReloading = false;
                },
            });
        };

        const startPolling = () => {
            if (interval === undefined) {
                interval = window.setInterval(reloadAnalytics, 30_000);
            }
        };

        const stopPolling = () => {
            if (interval !== undefined) {
                window.clearInterval(interval);
                interval = undefined;
            }
        };

        const handleVisibilityChange = () => {
            if (document.visibilityState === 'visible') {
                reloadAnalytics();
                startPolling();
            } else {
                stopPolling();
            }
        };

        if (document.visibilityState === 'visible') {
            startPolling();
        }

        document.addEventListener('visibilitychange', handleVisibilityChange);

        return () => {
            stopPolling();
            document.removeEventListener(
                'visibilitychange',
                handleVisibilityChange,
            );
        };
    }, []);

    const metricTotal =
        metric === 'engagements'
            ? analytics.totalClicks
            : analytics.totalEvents;
    const clicksPerVisitor =
        analytics.uniqueVisitors > 0
            ? analytics.totalClicks / analytics.uniqueVisitors
            : 0;

    return (
        <div className="grid gap-5">
            {props.mode === 'account' && (
                <AnalyticsFilterForm
                    filters={props.filters}
                    actionUrl={props.filterUrl}
                    exportUrl={props.exportUrl}
                />
            )}
            {props.mode !== 'account' && (
                <AnalyticsWindowPicker
                    days={analytics.days}
                    windowUrl={props.windowUrl}
                />
            )}

            <section
                aria-label="Analytics totals"
                className="grid grid-cols-2 gap-3 xl:grid-cols-4"
            >
                <StatCard
                    label="Clicks"
                    value={analytics.totalClicks.toLocaleString()}
                    note={`${analytics.days} days`}
                    sparkline={analytics.daily.map((day) => day.clicks)}
                    sparklineColor="var(--chart-2)"
                />
                <StatCard
                    label="Unique visitors"
                    value={analytics.uniqueVisitors.toLocaleString()}
                    note={`${analytics.days} days`}
                />
                <StatCard
                    label="Tracked events"
                    value={analytics.totalEvents.toLocaleString()}
                    note={`${analytics.days} days`}
                    sparkline={analytics.daily.map((day) => day.events)}
                    sparklineColor="var(--chart-4)"
                />
                <StatCard
                    label="Clicks per visitor"
                    value={clicksPerVisitor.toFixed(1)}
                    note={`${analytics.days} days`}
                />
            </section>

            <div className="grid min-w-0 gap-5">
                <Card className="min-w-0">
                    <CardHeader className="gap-4">
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div className="grid gap-1">
                                <CardTitle as="h2">
                                    {metric === 'engagements'
                                        ? 'Engagement over time'
                                        : 'Tracked events over time'}
                                </CardTitle>
                                <CardDescription>
                                    Daily{' '}
                                    {metric === 'engagements'
                                        ? 'clicks'
                                        : 'consented events'}{' '}
                                    in UTC.
                                </CardDescription>
                            </div>
                            <div className="flex flex-wrap items-center gap-2">
                                <SegmentedControl
                                    value={metric}
                                    onValueChange={setMetric}
                                    ariaLabel="Analytics metric"
                                    options={[
                                        {
                                            value: 'engagements',
                                            label: 'Engagements',
                                        },
                                        {
                                            value: 'events',
                                            label: 'Tracked events',
                                        },
                                    ]}
                                />
                                {props.mode === 'account' && (
                                    <Button asChild variant="ghost" size="sm">
                                        <Link href={props.eventsUrl}>
                                            Event records
                                            <ArrowUpRight aria-hidden="true" />
                                        </Link>
                                    </Button>
                                )}
                            </div>
                        </div>
                        <div className="flex flex-wrap items-end justify-between gap-4">
                            <div className="grid gap-0.5">
                                <span className="text-2xl leading-7 font-semibold tracking-[-0.02em] tabular-nums">
                                    {metricTotal.toLocaleString()}
                                </span>
                                <span className="text-xs text-muted-foreground">
                                    {metric === 'engagements'
                                        ? 'Clicks'
                                        : 'Tracked events'}{' '}
                                    · last {analytics.days} days
                                </span>
                            </div>
                            <span className="inline-flex items-center gap-1.5 text-xs text-muted-foreground">
                                <span
                                    className="size-2.5 rounded-full"
                                    style={{
                                        backgroundColor:
                                            metric === 'engagements'
                                                ? 'var(--chart-2)'
                                                : 'var(--chart-4)',
                                    }}
                                    aria-hidden="true"
                                />
                                {metric === 'engagements'
                                    ? 'Clicks'
                                    : 'Tracked events'}
                            </span>
                        </div>
                    </CardHeader>
                    <CardContent className="min-w-0">
                        <DailyChart
                            daily={analytics.daily}
                            metric={metric}
                            showDailyValues={props.mode === 'account'}
                        />
                    </CardContent>
                </Card>

                <TrafficSourcesSection
                    referrers={analytics.topReferrers}
                    countries={analytics.topCountries}
                    browsers={analytics.topBrowsers}
                    platforms={analytics.topPlatforms}
                />
            </div>
        </div>
    );
}
