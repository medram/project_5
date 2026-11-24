import { Head, Link, router } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';
import { Download } from 'lucide-react';
import { formatAdminDate } from '@/components/admin/format';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import logs from '@/routes/admin/logs';
import type { OperationLogsPageProps } from '@/types/operation-logs';

export default function AdminLogsIndex({
    rows,
    filters,
}: OperationLogsPageProps) {
    const [query, setQuery] = useState(filters.q);
    const [level, setLevel] = useState(filters.level ?? 'any');
    const [channel, setChannel] = useState(filters.channel ?? '');

    function apply(event: FormEvent) {
        event.preventDefault();
        router.get(
            logs.index().url,
            {
                q: query || undefined,
                level: level === 'any' ? undefined : level,
                channel: channel || undefined,
            },
            { preserveScroll: true },
        );
    }

    const exportUrl = logs.export({
        query: {
            q: query || undefined,
            level: level === 'any' ? undefined : level,
            channel: channel || undefined,
        },
    }).url;

    return (
        <>
            <Head title="Operational logs — Administration" />
            <div className="mx-auto flex w-full max-w-7xl min-w-0 flex-1 flex-col gap-5 px-5 pt-4 pb-10">
                <PageHeader
                    title="Operational logs"
                    description="Application-owned queue, scheduler, mail, and operations lifecycle records. Raw logs, request bodies, environment values, and secrets are never exposed."
                    actions={
                        <Button
                            asChild
                            variant="outline"
                            className="min-h-11 self-start"
                        >
                            <a href={exportUrl}>
                                <Download />
                                Export CSV
                            </a>
                        </Button>
                    }
                />
                <Card>
                    <CardHeader>
                        <CardTitle>Filters</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form
                            className="grid gap-3 sm:grid-cols-2 xl:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_minmax(0,1fr)_auto]"
                            onSubmit={apply}
                        >
                            <div className="grid gap-1">
                                <Label htmlFor="log-search">Search</Label>
                                <Input
                                    id="log-search"
                                    value={query}
                                    onChange={(e) => setQuery(e.target.value)}
                                />
                            </div>
                            <div className="grid gap-1">
                                <Label htmlFor="log-level">Level</Label>
                                <Select value={level} onValueChange={setLevel}>
                                    <SelectTrigger id="log-level">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="any">
                                            Any level
                                        </SelectItem>
                                        {[
                                            'debug',
                                            'info',
                                            'warning',
                                            'error',
                                            'critical',
                                        ].map((item) => (
                                            <SelectItem key={item} value={item}>
                                                {item}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="grid gap-1">
                                <Label htmlFor="log-channel">Channel</Label>
                                <Input
                                    id="log-channel"
                                    value={channel}
                                    onChange={(e) => setChannel(e.target.value)}
                                />
                            </div>
                            <Button type="submit" className="self-end">
                                Apply
                            </Button>
                        </form>
                    </CardContent>
                </Card>
                <div className="lp-table-shell hidden lg:block">
                    <table
                        data-table-surface={
                            rows.data.length > 0 ? 'populated' : undefined
                        }
                        className="w-full text-sm"
                    >
                        <thead className="bg-muted/40 text-left text-xs text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3">When</th>
                                <th className="px-4 py-3">Level</th>
                                <th className="px-4 py-3">Channel / event</th>
                                <th className="px-4 py-3">Message</th>
                                <th className="px-4 py-3">Correlation</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.data.map((row) => (
                                <tr key={row.id} className="border-t align-top">
                                    <td className="px-4 py-3 whitespace-nowrap text-muted-foreground">
                                        {formatAdminDate(row.createdAt)}
                                    </td>
                                    <td className="px-4 py-3">
                                        <Badge variant="outline">
                                            {row.level}
                                        </Badge>
                                    </td>
                                    <td className="px-4 py-3">
                                        <p>{row.channel}</p>
                                        <p className="font-mono text-xs text-muted-foreground">
                                            {row.event}
                                        </p>
                                    </td>
                                    <td className="max-w-96 px-4 py-3">
                                        <p>{row.message}</p>
                                        {row.context && (
                                            <p className="truncate font-mono text-xs text-muted-foreground">
                                                {JSON.stringify(row.context)}
                                            </p>
                                        )}
                                    </td>
                                    <td className="max-w-40 truncate px-4 py-3 font-mono text-xs text-muted-foreground">
                                        {row.correlationId ?? '—'}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    {rows.data.length === 0 && (
                        <p className="p-8 text-center text-sm text-muted-foreground">
                            No operational logs match these filters.
                        </p>
                    )}
                </div>
                <div className="grid gap-3 lg:hidden">
                    {rows.data.map((row) => (
                        <Card key={row.id}>
                            <CardHeader className="gap-2">
                                <div className="flex items-start justify-between gap-2">
                                    <div>
                                        <CardTitle className="text-base">
                                            {row.event}
                                        </CardTitle>
                                        <p className="text-xs text-muted-foreground">
                                            {formatAdminDate(row.createdAt)}
                                        </p>
                                    </div>
                                    <Badge variant="outline">{row.level}</Badge>
                                </div>
                            </CardHeader>
                            <CardContent className="grid gap-2 text-sm">
                                <p>{row.message}</p>
                                <p className="text-xs text-muted-foreground">
                                    {row.channel} ·{' '}
                                    {row.correlationId ?? 'no correlation'}
                                </p>
                                {row.context && (
                                    <pre className="overflow-auto rounded bg-muted p-2 text-xs">
                                        {JSON.stringify(row.context, null, 2)}
                                    </pre>
                                )}
                            </CardContent>
                        </Card>
                    ))}
                    {rows.data.length === 0 && (
                        <Card variant="transparent">
                            <CardContent className="p-8 text-center text-sm text-muted-foreground">
                                No operational logs match these filters.
                            </CardContent>
                        </Card>
                    )}
                </div>
                <footer className="flex flex-col gap-3 text-sm text-muted-foreground sm:flex-row sm:justify-between">
                    <p>
                        Showing {rows.from ?? 0}–{rows.to ?? 0} of{' '}
                        {rows.total.toLocaleString()}
                    </p>
                    {rows.last_page > 1 && (
                        <nav
                            className="flex flex-wrap gap-1"
                            aria-label="Logs pagination"
                        >
                            {rows.links.map((link) =>
                                link.url ? (
                                    <Link
                                        key={link.label}
                                        href={link.url}
                                        className={`rounded-md border px-3 py-2 ${link.active ? 'bg-primary text-primary-foreground' : 'hover:bg-muted'}`}
                                    >
                                        {link.label
                                            .replace('&laquo;', '‹')
                                            .replace('&raquo;', '›')}
                                    </Link>
                                ) : (
                                    <span
                                        key={link.label}
                                        className="rounded-md border px-3 py-2 opacity-50"
                                    >
                                        {link.label
                                            .replace('&laquo;', '‹')
                                            .replace('&raquo;', '›')}
                                    </span>
                                ),
                            )}
                        </nav>
                    )}
                </footer>
            </div>
        </>
    );
}
