import { QrCode } from 'lucide-react';
import { DailyLineChart } from '@/components/analytics/daily-line-chart';
import { TrafficSourcesSection } from '@/components/analytics/traffic-sources';
import { AnalyticsWindowPicker } from '@/components/analytics/window-picker';
import { StatCard } from '@/components/dashboard/stat-card';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { AnalyticsWindowDays } from '@/types/analytics';
import type { QrCodeAnalytics } from '@/types/qr-codes';

export function QrScanInsights({
    analytics,
    windowUrl,
}: {
    analytics: QrCodeAnalytics;
    windowUrl: (days: AnalyticsWindowDays) => string;
}) {
    return (
        <div className="grid gap-5">
            <AnalyticsWindowPicker
                days={analytics.days}
                windowUrl={windowUrl}
            />

            <section
                aria-label="QR scan totals"
                className="grid grid-cols-2 gap-3 xl:grid-cols-4"
            >
                <StatCard
                    label="Scans"
                    value={analytics.totalScans.toLocaleString()}
                    note={`${analytics.days} days`}
                    sparkline={analytics.daily.map((day) => day.scans)}
                    sparklineColor="var(--chart-2)"
                />
                <StatCard
                    label="Countries"
                    value={analytics.countryCount.toLocaleString()}
                    note={`${analytics.days} days`}
                />
                <StatCard
                    label="Browsers"
                    value={analytics.browserCount.toLocaleString()}
                    note={`${analytics.days} days`}
                />
                <StatCard
                    label="Devices"
                    value={analytics.deviceCount.toLocaleString()}
                    note={`${analytics.days} days`}
                />
            </section>

            <Card className="min-w-0">
                <CardHeader className="gap-1.5">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div className="grid gap-1">
                            <CardTitle
                                as="h2"
                                className="flex items-center gap-2"
                            >
                                <QrCode
                                    className="size-4 shrink-0 text-muted-foreground"
                                    aria-hidden="true"
                                />
                                Scans over time
                            </CardTitle>
                            <CardDescription>
                                Successful QR visits per UTC day.
                            </CardDescription>
                        </div>
                        <span className="inline-flex items-center gap-1.5 text-xs text-muted-foreground">
                            <span
                                className="size-2.5 rounded-full"
                                style={{ backgroundColor: 'var(--chart-2)' }}
                                aria-hidden="true"
                            />
                            Scans
                        </span>
                    </div>
                </CardHeader>
                <CardContent className="min-w-0">
                    <DailyLineChart
                        data={analytics.daily}
                        dataKey="scans"
                        label="Scans"
                        ariaLabel="Daily QR scans for the selected UTC window."
                        emptyTitle="No scans in this window"
                        emptyDescription="Choose another window or check again after the QR code is scanned."
                        color="var(--chart-2)"
                        icon={QrCode}
                    />
                </CardContent>
            </Card>

            <p className="text-xs leading-4 text-muted-foreground">
                Scan dimensions use coarse request data only; no cookies or raw
                IPs are stored. Scans recorded before this data was collected
                still count in totals and the daily trend.
            </p>

            <TrafficSourcesSection
                referrers={analytics.topReferrers}
                countries={analytics.topCountries}
                browsers={analytics.topBrowsers}
                platforms={analytics.topPlatforms}
                devices={analytics.topDevices}
                metric="scans"
            />
        </div>
    );
}
