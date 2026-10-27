import { Link, router } from '@inertiajs/react';
import {
    type FormEvent,
    type KeyboardEvent,
    useEffect,
    useId,
    useRef,
    useState,
} from 'react';
import { BarChart3 } from 'lucide-react';
import { CartesianGrid, Line, LineChart, XAxis, YAxis } from 'recharts';
import { Button } from '@/components/ui/button';
import {
    ChartContainer,
    ChartTooltip,
    ChartTooltipContent,
    type ChartConfig,
} from '@/components/ui/chart';
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
import type {
    AnalyticsCount,
    AnalyticsDaily,
    AnalyticsFilters,
    AnalyticsInsightsResult,
    AnalyticsMetric,
    AnalyticsResourceType,
    AnalyticsWindowDays,
} from '@/types/analytics';

const dailyChartConfig = {
    clicks: { label: 'Clicks', color: 'var(--chart-1)' },
    events: { label: 'Tracked events', color: 'var(--chart-2)' },
} satisfies ChartConfig;

function DailyChart({
    daily,
    metric,
}: {
    daily: AnalyticsDaily[];
    metric: AnalyticsMetric;
}) {
    const dataKey = metric === 'engagements' ? 'clicks' : 'events';
    const label = metric === 'engagements' ? 'clicks' : 'tracked events';
    const hasData = daily.some((day) => day[dataKey] > 0);

    return (
        <div className="grid min-w-0 gap-4">
            {hasData ? (
                <ChartContainer
                    config={dailyChartConfig}
                    className="h-[260px] w-full min-w-0"
                    aria-label={`Daily ${label} for the selected UTC window. Exact values are listed below.`}
                >
                    <LineChart
                        data={daily}
                        margin={{ top: 8, right: 8, bottom: 0, left: 8 }}
                    >
                        <CartesianGrid vertical={false} />
                        <XAxis
                            dataKey="date"
                            axisLine={false}
                            tickLine={false}
                            minTickGap={24}
                            tickMargin={8}
                            tickFormatter={(value) => formatDay(String(value))}
                        />
                        <YAxis
                            allowDecimals={false}
                            axisLine={false}
                            tickLine={false}
                            width={40}
                        />
                        <ChartTooltip
                            cursor={false}
                            content={
                                <ChartTooltipContent
                                    labelFormatter={(value) =>
                                        formatDay(String(value))
                                    }
                                />
                            }
                        />
                        <Line
                            dataKey={dataKey}
                            type="monotone"
                            stroke={`var(--color-${dataKey})`}
                            strokeWidth={2}
                            dot={false}
                            activeDot={{ r: 4 }}
                            isAnimationActive={false}
                        />
                    </LineChart>
                </ChartContainer>
            ) : (
                <Empty className="bg-muted/30">
                    <EmptyHeader>
                        <EmptyMedia variant="icon">
                            <BarChart3 aria-hidden="true" />
                        </EmptyMedia>
                        <EmptyTitle>No {label} in this window</EmptyTitle>
                        <EmptyDescription>
                            Choose another window or check again after visitors
                            interact.
                        </EmptyDescription>
                    </EmptyHeader>
                </Empty>
            )}
            <div className="max-h-64 overflow-y-auto rounded-md border">
                <table aria-label={`Daily ${label}`} className="w-full text-sm">
                    <thead className="sticky top-0 bg-background text-left">
                        <tr>
                            <th className="px-3 py-2 font-medium">UTC date</th>
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
                                        {formatDay(day.date)}
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

const dateWindows: readonly AnalyticsWindowDays[] = [7, 30, 90];

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

    return (
        <Card>
            <CardHeader>
                <CardTitle>Filter analytics</CardTitle>
                <CardDescription>
                    Event, device, country, and UTM filters narrow tracked
                    events and event records. Click totals use the selected date
                    range and resource only.
                </CardDescription>
            </CardHeader>
            <CardContent>
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
                                            {dateWindows.map((days) => (
                                                <SelectItem
                                                    key={days}
                                                    value={String(days)}
                                                >
                                                    Last {days} days
                                                </SelectItem>
                                            ))}
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
            </CardContent>
        </Card>
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
    const tabListId = useId();
    const tabRefs = useRef<Record<AnalyticsMetric, HTMLButtonElement | null>>({
        engagements: null,
        events: null,
    });

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

    function handleMetricTabKeyDown(event: KeyboardEvent<HTMLDivElement>) {
        const metrics: AnalyticsMetric[] = ['engagements', 'events'];
        const currentIndex = metrics.indexOf(metric);
        let nextMetric: AnalyticsMetric;

        switch (event.key) {
            case 'ArrowRight':
                nextMetric = metrics[(currentIndex + 1) % metrics.length];
                break;
            case 'ArrowLeft':
                nextMetric =
                    metrics[
                        (currentIndex - 1 + metrics.length) % metrics.length
                    ];
                break;
            case 'Home':
                nextMetric = metrics[0];
                break;
            case 'End':
                nextMetric = metrics[metrics.length - 1];
                break;
            default:
                return;
        }

        event.preventDefault();
        setMetric(nextMetric);
        tabRefs.current[nextMetric]?.focus();
    }

    return (
        <div className="grid gap-6">
            {props.mode === 'account' && (
                <AnalyticsFilterForm
                    filters={props.filters}
                    actionUrl={props.filterUrl}
                    exportUrl={props.exportUrl}
                />
            )}
            <div className="flex flex-wrap items-center justify-between gap-3">
                {props.mode === 'account' ? (
                    <Button asChild variant="outline">
                        <Link href={props.eventsUrl}>View event records</Link>
                    </Button>
                ) : (
                    <div
                        role="group"
                        aria-label="Date range"
                        className="flex rounded-md border p-1"
                    >
                        {dateWindows.map((days) => (
                            <Button
                                key={days}
                                asChild
                                size="sm"
                                variant={
                                    analytics.days === days
                                        ? 'default'
                                        : 'ghost'
                                }
                            >
                                <Link
                                    href={props.windowUrl(days)}
                                    aria-current={
                                        analytics.days === days
                                            ? 'page'
                                            : undefined
                                    }
                                >
                                    {days} days
                                </Link>
                            </Button>
                        ))}
                    </div>
                )}
                <div
                    id={tabListId}
                    role="tablist"
                    aria-label="Analytics metric"
                    onKeyDown={handleMetricTabKeyDown}
                    className="flex rounded-md border p-1"
                >
                    {(['engagements', 'events'] as const).map((value) => (
                        <button
                            key={value}
                            id={`${tabListId}-${value}`}
                            ref={(element) => {
                                tabRefs.current[value] = element;
                            }}
                            type="button"
                            role="tab"
                            aria-selected={metric === value}
                            aria-controls={`${tabListId}-panel`}
                            tabIndex={metric === value ? 0 : -1}
                            onClick={() => setMetric(value)}
                            className="min-h-9 rounded px-3 text-sm font-medium capitalize aria-selected:bg-primary aria-selected:text-primary-foreground"
                        >
                            {value === 'events'
                                ? 'Tracked events'
                                : 'Engagements'}
                        </button>
                    ))}
                </div>
            </div>

            <section
                aria-label="Analytics totals"
                className="grid gap-4 sm:grid-cols-3"
            >
                <Card>
                    <CardHeader>
                        <CardTitle>Clicks</CardTitle>
                        <CardDescription>
                            All successful short-link deliveries.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="text-4xl font-semibold tabular-nums">
                        {analytics.totalClicks.toLocaleString()}
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle>Unique visitors</CardTitle>
                        <CardDescription>
                            Distinct consented visitor hashes.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="text-4xl font-semibold tabular-nums">
                        {analytics.uniqueVisitors.toLocaleString()}
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle>Tracked events</CardTitle>
                        <CardDescription>
                            Consented activity across public surfaces.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="text-4xl font-semibold tabular-nums">
                        {analytics.totalEvents.toLocaleString()}
                    </CardContent>
                </Card>
            </section>

            <div
                id={`${tabListId}-panel`}
                role="tabpanel"
                aria-labelledby={`${tabListId}-${metric}`}
                tabIndex={0}
                className="grid min-w-0 gap-6"
            >
                <Card className="min-w-0">
                    <CardHeader>
                        <CardTitle>
                            {metric === 'engagements'
                                ? 'Daily clicks'
                                : 'Daily tracked events'}
                        </CardTitle>
                        <CardDescription>
                            {analytics.days} UTC calendar days, including today.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="min-w-0">
                        <DailyChart daily={analytics.daily} metric={metric} />
                    </CardContent>
                </Card>

                {metric === 'events' && (
                    <section
                        aria-label="Tracked event dimensions"
                        className="grid gap-4 md:grid-cols-2 xl:grid-cols-4"
                    >
                        <CountList
                            title="Events by name"
                            rows={analytics.eventsByName}
                        />
                        <CountList
                            title="Top referrers"
                            rows={analytics.topReferrers}
                        />
                        <CountList
                            title="Top browsers"
                            rows={analytics.topBrowsers}
                        />
                        <CountList
                            title="Top platforms"
                            rows={analytics.topPlatforms}
                        />
                    </section>
                )}
            </div>
        </div>
    );
}

function formatDay(date: string): string {
    return new Intl.DateTimeFormat(undefined, {
        month: 'short',
        day: 'numeric',
    }).format(new Date(`${date}T12:00:00Z`));
}

function CountList({ title, rows }: { title: string; rows: AnalyticsCount[] }) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>{title}</CardTitle>
            </CardHeader>
            <CardContent>
                {rows.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        No consented data in this window.
                    </p>
                ) : (
                    <ol className="grid gap-2">
                        {rows.map((row) => (
                            <li
                                key={row.name}
                                className="flex items-center justify-between gap-3 text-sm"
                            >
                                <span className="truncate">{row.name}</span>
                                <span className="font-medium tabular-nums">
                                    {row.count.toLocaleString()}
                                </span>
                            </li>
                        ))}
                    </ol>
                )}
            </CardContent>
        </Card>
    );
}
