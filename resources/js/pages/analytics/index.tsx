import { Head } from '@inertiajs/react';
import { BarChart3, ShieldCheck } from 'lucide-react';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { index } from '@/routes/analytics';
import type { AccountAnalyticsProps } from '@/types/analytics';

const windows = [7, 30, 90] as const;

export default function AccountAnalytics({
    windows: analyticsWindows,
}: AccountAnalyticsProps) {
    return (
        <>
            <Head title="Analytics" />

            <main className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <section className="max-w-2xl space-y-2">
                    <div className="flex items-center gap-2 text-linkpilot-creator">
                        <BarChart3 aria-hidden="true" />
                        <span className="text-sm font-medium">
                            Privacy-first insights
                        </span>
                    </div>
                    <h1 className="text-3xl font-semibold tracking-tight">
                        Analytics
                    </h1>
                    <p className="text-sm leading-6 text-muted-foreground">
                        Consented visits across your public LinkPilot surfaces.
                        Direct short-link clicks remain available in each link’s
                        insights.
                    </p>
                </section>

                <section
                    aria-label="Analytics windows"
                    className="grid gap-4 md:grid-cols-3"
                >
                    {windows.map((days) => (
                        <Card key={days}>
                            <CardHeader>
                                <CardTitle>Last {days} days</CardTitle>
                                <CardDescription>
                                    UTC calendar days, including today.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <p className="text-4xl font-semibold tracking-tight tabular-nums">
                                    {analyticsWindows[
                                        days
                                    ].total.toLocaleString()}
                                </p>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    consented event
                                    {analyticsWindows[days].total === 1
                                        ? ''
                                        : 's'}
                                </p>
                            </CardContent>
                        </Card>
                    ))}
                </section>

                <Card className="max-w-3xl">
                    <CardHeader>
                        <div className="flex items-center gap-2">
                            <ShieldCheck
                                className="size-5 text-primary"
                                aria-hidden="true"
                            />
                            <CardTitle>What this includes</CardTitle>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <p className="text-sm leading-6 text-muted-foreground">
                            Events are recorded only after a visitor grants
                            consent. They retain a keyed visitor hash and coarse
                            device dimensions, never a raw IP address, full user
                            agent, destination URL, or access code.
                        </p>
                    </CardContent>
                </Card>
            </main>
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
