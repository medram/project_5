import { router } from '@inertiajs/react';
import { type FormEvent, useEffect, useState } from 'react';
import { CalendarRange } from 'lucide-react';
import { Button } from '@/components/ui/button';
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
}: {
    range: AdminInsightsRange;
    url: string;
}) {
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
            className="flex flex-col gap-3 rounded-lg border p-4 lg:flex-row lg:items-end"
        >
            <div className="flex items-center gap-2 text-sm font-medium lg:mr-auto">
                <CalendarRange className="size-4" aria-hidden="true" />
                <span>{range.label}</span>
                <span className="text-xs font-normal text-muted-foreground">
                    UTC
                </span>
            </div>
            <div className="grid gap-2 sm:grid-cols-3 lg:flex">
                <div className="grid gap-1">
                    <Label htmlFor="insights-range">Window</Label>
                    <Select value={preset} onValueChange={setPreset}>
                        <SelectTrigger
                            id="insights-range"
                            className="min-h-9 w-full lg:w-40"
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
                </div>
                {preset === 'custom' && (
                    <>
                        <div className="grid gap-1">
                            <Label htmlFor="insights-from">From</Label>
                            <Input
                                id="insights-from"
                                type="date"
                                value={from}
                                required
                                onChange={(event) =>
                                    setFrom(event.target.value)
                                }
                            />
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor="insights-to">To</Label>
                            <Input
                                id="insights-to"
                                type="date"
                                value={to}
                                required
                                onChange={(event) => setTo(event.target.value)}
                            />
                        </div>
                    </>
                )}
                <Button type="submit" className="min-h-9 self-end">
                    Apply
                </Button>
            </div>
        </form>
    );
}
