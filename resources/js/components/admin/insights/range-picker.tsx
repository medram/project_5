import { router } from '@inertiajs/react';
import { type FormEvent, useEffect, useState } from 'react';
import { CalendarRange } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { AdminInsightsRange } from '@/types/admin-insights';

export function InsightsRangePicker({
    range,
    url,
    variant = 'panel',
}: {
    range: AdminInsightsRange;
    url: string;
    variant?: 'panel' | 'inline';
}) {
    const inline = variant === 'inline';
    const [preset, setPreset] = useState(range.preset);
    const [from, setFrom] = useState(range.from);
    const [to, setTo] = useState(range.to);

    useEffect(() => {
        setPreset(range.preset);
        setFrom(range.from);
        setTo(range.to);
    }, [range]);

    function apply(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.get(
            url,
            {
                range: preset,
                ...(preset === 'custom' ? { from, to } : {}),
            },
            { preserveScroll: true },
        );
    }

    return (
        <form
            onSubmit={apply}
            className={cn(
                inline
                    ? 'flex flex-wrap items-center gap-2'
                    : 'flex flex-col gap-3 rounded-2xl border border-border bg-card px-4 py-3 shadow-[var(--shadow-surface)] sm:flex-row sm:items-center',
            )}
        >
            {!inline && (
                <div className="hidden items-center gap-2 text-sm font-medium sm:mr-auto sm:flex">
                    <CalendarRange
                        className="size-4 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <span>{range.label}</span>
                    <span className="text-xs font-normal text-muted-foreground">
                        UTC
                    </span>
                </div>
            )}
            <div className="flex flex-wrap items-end gap-2">
                <div
                    className={
                        inline ? 'flex items-center gap-1.5' : 'grid gap-1'
                    }
                >
                    <Label
                        htmlFor="insights-range"
                        className={cn(
                            'text-xs text-muted-foreground',
                            inline && 'sr-only',
                        )}
                    >
                        Window
                    </Label>
                    <Select value={preset} onValueChange={setPreset}>
                        <SelectTrigger
                            id="insights-range"
                            size={inline ? 'default' : 'sm'}
                            className={inline ? 'w-40' : 'w-full sm:w-40'}
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="7">Last 7 days</SelectItem>
                            <SelectItem value="30">Last 30 days</SelectItem>
                            <SelectItem value="90">Last 90 days</SelectItem>
                            <SelectItem value="custom">Custom range</SelectItem>
                        </SelectContent>
                    </Select>
                    {inline ? (
                        <span className="text-xs text-muted-foreground">
                            UTC
                        </span>
                    ) : null}
                </div>
                {preset === 'custom' && (
                    <>
                        <div className={inline ? 'contents' : 'grid gap-1'}>
                            <Label
                                htmlFor="insights-from"
                                className={cn(
                                    'text-xs text-muted-foreground',
                                    inline && 'sr-only',
                                )}
                            >
                                From
                            </Label>
                            <Input
                                id="insights-from"
                                type="date"
                                className={inline ? 'h-9 w-36' : 'h-8'}
                                value={from}
                                required
                                onChange={(event) =>
                                    setFrom(event.target.value)
                                }
                            />
                        </div>
                        <div className={inline ? 'contents' : 'grid gap-1'}>
                            <Label
                                htmlFor="insights-to"
                                className={cn(
                                    'text-xs text-muted-foreground',
                                    inline && 'sr-only',
                                )}
                            >
                                To
                            </Label>
                            <Input
                                id="insights-to"
                                type="date"
                                className={inline ? 'h-9 w-36' : 'h-8'}
                                value={to}
                                required
                                onChange={(event) => setTo(event.target.value)}
                            />
                        </div>
                    </>
                )}
                <Button
                    type="submit"
                    size={inline ? 'default' : 'sm'}
                    className={inline ? undefined : 'h-8'}
                >
                    Apply
                </Button>
            </div>
        </form>
    );
}
