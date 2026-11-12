import { Head, Link, router, usePage } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';
import { FileSearch, SlidersHorizontal } from 'lucide-react';
import { formatAdminDate } from '@/components/admin/format';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { AdminContentPageProps } from '@/types/admin-content';

export default function AdminContentIndex({
    resource,
    rows,
    filters,
}: AdminContentPageProps) {
    const { url } = usePage();
    const path = url.split('?')[0];
    const [query, setQuery] = useState(filters.q ?? '');
    const [state, setState] = useState(filters.state ?? 'any');
    const [sort, setSort] = useState(filters.sort);
    const [direction, setDirection] = useState(filters.direction);

    function apply(event: FormEvent) {
        event.preventDefault();
        router.get(
            path,
            {
                q: query.trim() || undefined,
                state: state === 'any' ? undefined : state,
                sort,
                direction,
            },
            { preserveScroll: true, preserveState: true },
        );
    }

    function reset() {
        setQuery('');
        setState('any');
        setSort('updated_at');
        setDirection('desc');
        router.get(path, {}, { preserveScroll: true });
    }

    return (
        <>
            <Head title={`${resource.title} — Administration`} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <header className="space-y-1">
                    <div className="flex items-center gap-2 text-sm text-muted-foreground">
                        <FileSearch className="size-4" aria-hidden="true" />
                        <span>Platform content metadata</span>
                    </div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        {resource.title}
                    </h1>
                    <p className="max-w-3xl text-sm text-muted-foreground">
                        {resource.description} This is a read-only cross-owner
                        view; administrators are not attached to tenant
                        workspaces.
                    </p>
                </header>

                <Card>
                    <CardHeader className="gap-1">
                        <CardTitle className="flex items-center gap-2">
                            <SlidersHorizontal
                                className="size-4"
                                aria-hidden="true"
                            />
                            Filters
                        </CardTitle>
                        <CardDescription>
                            Search, state, and ordering are stored in the URL.
                            Only aggregate counts and public identifiers are
                            returned.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form
                            onSubmit={apply}
                            className="grid gap-3 sm:grid-cols-2 xl:grid-cols-[minmax(0,2fr)_repeat(3,minmax(0,1fr))_auto]"
                        >
                            <div className="grid gap-2">
                                <Label htmlFor="content-search">Search</Label>
                                <Input
                                    id="content-search"
                                    value={query}
                                    maxLength={100}
                                    placeholder="Name, public identifier, workspace, or owner"
                                    onChange={(event) =>
                                        setQuery(event.target.value)
                                    }
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="content-state">State</Label>
                                <Select value={state} onValueChange={setState}>
                                    <SelectTrigger
                                        id="content-state"
                                        className="min-h-9 w-full"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="any">
                                            Any state
                                        </SelectItem>
                                        {resource.stateOptions.map((option) => (
                                            <SelectItem
                                                key={option.value}
                                                value={option.value}
                                            >
                                                {option.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="content-sort">Sort</Label>
                                <Select value={sort} onValueChange={setSort}>
                                    <SelectTrigger
                                        id="content-sort"
                                        className="min-h-9 w-full"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="updated_at">
                                            Updated
                                        </SelectItem>
                                        <SelectItem value="created_at">
                                            Created
                                        </SelectItem>
                                        <SelectItem value="name">
                                            Name
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="content-direction">
                                    Direction
                                </Label>
                                <Select
                                    value={direction}
                                    onValueChange={setDirection}
                                >
                                    <SelectTrigger
                                        id="content-direction"
                                        className="min-h-9 w-full"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="desc">
                                            Descending
                                        </SelectItem>
                                        <SelectItem value="asc">
                                            Ascending
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="flex items-end gap-2">
                                <Button
                                    type="submit"
                                    className="min-h-9 flex-1"
                                >
                                    Apply
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    className="min-h-9"
                                    onClick={reset}
                                >
                                    Reset
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <div className="hidden overflow-hidden rounded-lg border lg:block">
                    <table className="w-full border-collapse text-sm">
                        <thead className="bg-muted/50 text-left text-xs text-muted-foreground uppercase">
                            <tr>
                                <th className="px-4 py-3 font-medium">
                                    {resource.identifierLabel
                                        ? `Resource / ${resource.identifierLabel}`
                                        : 'Resource'}
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Workspace / owner
                                </th>
                                <th className="px-4 py-3 font-medium">State</th>
                                <th className="px-4 py-3 font-medium">
                                    Type / provider
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Counts
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Updated
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.data.map((row) => (
                                <tr
                                    key={`${row.workspace}-${row.name}-${row.identifier}-${row.createdAt}`}
                                    className="border-t align-top"
                                >
                                    <td className="max-w-72 px-4 py-3">
                                        <p className="truncate font-medium">
                                            {row.name}
                                        </p>
                                        {row.identifier && (
                                            <p className="truncate text-xs text-muted-foreground">
                                                {row.identifier}
                                            </p>
                                        )}
                                        {Object.entries(row.extra).map(
                                            ([label, value]) =>
                                                value && (
                                                    <p
                                                        key={label}
                                                        className="text-xs text-muted-foreground"
                                                    >
                                                        {label}:{' '}
                                                        {formatAdminDate(value)}
                                                    </p>
                                                ),
                                        )}
                                    </td>
                                    <td className="max-w-64 px-4 py-3">
                                        <p className="truncate">
                                            {row.workspace ?? '—'}
                                        </p>
                                        <p className="truncate text-xs text-muted-foreground">
                                            {row.owner ?? 'No owner'}
                                        </p>
                                    </td>
                                    <td className="px-4 py-3">
                                        <Badge variant="outline">
                                            {row.state}
                                        </Badge>
                                    </td>
                                    <td className="px-4 py-3">
                                        <p>{row.type}</p>
                                        {row.provider && (
                                            <p className="text-xs text-muted-foreground">
                                                {row.provider}
                                            </p>
                                        )}
                                    </td>
                                    <td className="px-4 py-3">
                                        {row.counts.length === 0 ? (
                                            <span className="text-muted-foreground">
                                                —
                                            </span>
                                        ) : (
                                            <div className="grid gap-0.5 text-xs">
                                                {row.counts.map((count) => (
                                                    <span key={count.label}>
                                                        {count.label}:{' '}
                                                        <span className="font-medium tabular-nums">
                                                            {count.value.toLocaleString()}
                                                        </span>
                                                    </span>
                                                ))}
                                            </div>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-muted-foreground">
                                        {formatAdminDate(row.updatedAt)}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    {rows.data.length === 0 && (
                        <p className="p-8 text-center text-sm text-muted-foreground">
                            No {resource.title.toLowerCase()} match these
                            filters.
                        </p>
                    )}
                </div>

                <div className="grid gap-3 lg:hidden">
                    {rows.data.map((row) => (
                        <Card
                            key={`${row.workspace}-${row.name}-${row.createdAt}`}
                        >
                            <CardHeader className="gap-2">
                                <div className="flex items-start justify-between gap-2">
                                    <div className="min-w-0">
                                        <CardTitle className="truncate text-base">
                                            {row.name}
                                        </CardTitle>
                                        {row.identifier && (
                                            <CardDescription className="truncate">
                                                {row.identifier}
                                            </CardDescription>
                                        )}
                                    </div>
                                    <Badge variant="outline">{row.state}</Badge>
                                </div>
                            </CardHeader>
                            <CardContent className="grid gap-2 text-sm">
                                <p className="truncate">
                                    {row.workspace ?? '—'}
                                </p>
                                <p className="truncate text-xs text-muted-foreground">
                                    {row.owner ?? 'No owner'}
                                </p>
                                <p>
                                    {row.type}
                                    {row.provider ? ` · ${row.provider}` : ''}
                                </p>
                                {row.counts.length > 0 && (
                                    <p className="text-xs text-muted-foreground">
                                        {row.counts
                                            .map(
                                                (count) =>
                                                    `${count.label}: ${count.value}`,
                                            )
                                            .join(' · ')}
                                    </p>
                                )}
                                {Object.entries(row.extra).map(
                                    ([label, value]) =>
                                        value && (
                                            <p
                                                key={label}
                                                className="text-xs text-muted-foreground"
                                            >
                                                {label}:{' '}
                                                {formatAdminDate(value)}
                                            </p>
                                        ),
                                )}
                                <p className="text-xs text-muted-foreground">
                                    Updated {formatAdminDate(row.updatedAt)}
                                </p>
                            </CardContent>
                        </Card>
                    ))}
                    {rows.data.length === 0 && (
                        <Card>
                            <CardContent className="p-8 text-center text-sm text-muted-foreground">
                                No {resource.title.toLowerCase()} match these
                                filters.
                            </CardContent>
                        </Card>
                    )}
                </div>

                <footer className="flex flex-col gap-3 text-sm text-muted-foreground sm:flex-row sm:items-center sm:justify-between">
                    <p>
                        Showing {rows.from ?? 0}–{rows.to ?? 0} of{' '}
                        {rows.total.toLocaleString()}
                    </p>
                    {rows.last_page > 1 && (
                        <nav className="flex flex-wrap gap-1">
                            {rows.links.map((link) =>
                                link.url === null ? (
                                    <span
                                        key={link.label}
                                        className="rounded-md border px-3 py-2 opacity-50"
                                    >
                                        {link.label
                                            .replace('&laquo;', '‹')
                                            .replace('&raquo;', '›')}
                                    </span>
                                ) : (
                                    <Link
                                        key={link.label}
                                        href={link.url}
                                        preserveScroll
                                        className={`min-h-9 rounded-md border px-3 py-2 ${
                                            link.active
                                                ? 'bg-primary text-primary-foreground'
                                                : 'hover:bg-muted'
                                        }`}
                                    >
                                        {link.label
                                            .replace('&laquo;', '‹')
                                            .replace('&raquo;', '›')}
                                    </Link>
                                ),
                            )}
                        </nav>
                    )}
                </footer>
            </div>
        </>
    );
}
