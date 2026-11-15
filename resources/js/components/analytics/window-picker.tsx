import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import type { AnalyticsWindowDays } from '@/types/analytics';

export const analyticsDateWindows: readonly AnalyticsWindowDays[] = [7, 30, 90];

export function AnalyticsWindowPicker({
    days,
    windowUrl,
}: {
    days: AnalyticsWindowDays;
    windowUrl: (days: AnalyticsWindowDays) => string;
}) {
    return (
        <div
            role="group"
            aria-label="Date range"
            className="flex w-fit items-center gap-0.5 rounded-lg border bg-muted/50 p-0.5"
        >
            {analyticsDateWindows.map((window) => (
                <Button
                    key={window}
                    asChild
                    size="sm"
                    variant={days === window ? 'default' : 'ghost'}
                >
                    <Link
                        href={windowUrl(window)}
                        aria-current={days === window ? 'page' : undefined}
                    >
                        {window} days
                    </Link>
                </Button>
            ))}
        </div>
    );
}
