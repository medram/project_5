import { Head, Link, router } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';
import { BookOpenText, ExternalLink, Plus, Trash2 } from 'lucide-react';
import {
    AdminConfirmDialog,
    type AdminConfirmation,
} from '@/components/admin/admin-confirm-dialog';
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
import pages from '@/routes/admin/pages';
import type { AdminPagesPageProps } from '@/types/admin-pages';

export default function AdminPagesIndex({
    pages: paginator,
    filters,
}: AdminPagesPageProps) {
    const [query, setQuery] = useState(filters.q);
    const [state, setState] = useState(filters.state ?? 'any');
    const [placement, setPlacement] = useState(filters.placement ?? 'any');
    const [pending, setPending] = useState<{
        confirmation: AdminConfirmation;
        run: () => void;
    } | null>(null);

    function apply(event: FormEvent) {
        event.preventDefault();
        router.get(
            pages.index().url,
            {
                q: query.trim() || undefined,
                state: state === 'any' ? undefined : state,
                placement: placement === 'any' ? undefined : placement,
            },
            { preserveScroll: true },
        );
    }

    function publish(id: number, title: string): void {
        setPending({
            confirmation: {
                title: `Publish ${title}?`,
                description:
                    'The page becomes publicly available at /content/{slug} from the current time. Draft content remains unchanged.',
                confirmLabel: 'Publish page',
            },
            run: () =>
                router.patch(
                    pages.publish(id).url,
                    {},
                    {
                        preserveScroll: true,
                        onSuccess: () => setPending(null),
                    },
                ),
        });
    }

    function destroy(id: number, title: string): void {
        setPending({
            confirmation: {
                title: `Delete ${title}?`,
                description:
                    'This permanently removes the custom page and its sanitized body. This action is audited.',
                confirmLabel: 'Delete page',
                destructive: true,
            },
            run: () =>
                router.delete(pages.destroy(id).url, {
                    preserveScroll: true,
                    onSuccess: () => setPending(null),
                }),
        });
    }

    return (
        <>
            <Head title="Custom pages — Administration" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <header className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div className="space-y-1">
                        <div className="flex items-center gap-2 text-sm text-muted-foreground">
                            <BookOpenText
                                className="size-4"
                                aria-hidden="true"
                            />
                            <span>Platform administration</span>
                        </div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Custom pages
                        </h1>
                        <p className="max-w-3xl text-sm text-muted-foreground">
                            Draft and publish sanitized application pages.
                            Public pages are served only from published records;
                            reserved application slugs cannot be claimed.
                        </p>
                    </div>
                    <Button asChild className="min-h-11 self-start">
                        <Link href={pages.create().url}>
                            <Plus aria-hidden="true" />
                            New page
                        </Link>
                    </Button>
                </header>

                <form
                    onSubmit={apply}
                    className="grid gap-3 rounded-lg border p-4 sm:grid-cols-2 xl:grid-cols-[minmax(0,2fr)_repeat(2,minmax(0,1fr))_auto]"
                >
                    <div className="grid gap-2">
                        <Label htmlFor="pages-search">Search</Label>
                        <Input
                            id="pages-search"
                            value={query}
                            maxLength={100}
                            placeholder="Title or slug"
                            onChange={(event) => setQuery(event.target.value)}
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="pages-state">State</Label>
                        <Select value={state} onValueChange={setState}>
                            <SelectTrigger
                                id="pages-state"
                                className="min-h-9 w-full"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="any">Any state</SelectItem>
                                <SelectItem value="draft">Draft</SelectItem>
                                <SelectItem value="published">
                                    Published
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="pages-placement">Placement</Label>
                        <Select value={placement} onValueChange={setPlacement}>
                            <SelectTrigger
                                id="pages-placement"
                                className="min-h-9 w-full"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="any">Any</SelectItem>
                                <SelectItem value="header">Header</SelectItem>
                                <SelectItem value="footer">Footer</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <Button type="submit" className="min-h-9 self-end">
                        Apply
                    </Button>
                </form>

                <div className="hidden overflow-hidden rounded-lg border lg:block">
                    <table className="w-full border-collapse text-sm">
                        <thead className="bg-muted/50 text-left text-xs text-muted-foreground uppercase">
                            <tr>
                                <th className="px-4 py-3 font-medium">Page</th>
                                <th className="px-4 py-3 font-medium">
                                    State / placement
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Updated by
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Published / updated
                                </th>
                                <th className="px-4 py-3 text-right font-medium">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {paginator.data.map((page) => (
                                <tr
                                    key={page.id}
                                    className="border-t align-top"
                                >
                                    <td className="px-4 py-3">
                                        <p className="font-medium">
                                            {page.title}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            /content/{page.slug}
                                        </p>
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex flex-wrap gap-1">
                                            <Badge
                                                variant={
                                                    page.state === 'published'
                                                        ? 'secondary'
                                                        : 'outline'
                                                }
                                            >
                                                {page.state}
                                            </Badge>
                                            {page.placement && (
                                                <Badge variant="outline">
                                                    {page.placement}
                                                </Badge>
                                            )}
                                        </div>
                                    </td>
                                    <td className="px-4 py-3 text-muted-foreground">
                                        {page.updater ?? page.creator ?? '—'}
                                    </td>
                                    <td className="px-4 py-3 text-muted-foreground">
                                        <p>
                                            {formatAdminDate(page.publishedAt)}
                                        </p>
                                        <p className="text-xs">
                                            {formatAdminDate(page.updatedAt)}
                                        </p>
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex flex-wrap justify-end gap-1">
                                            <Button
                                                asChild
                                                size="sm"
                                                variant="outline"
                                                className="min-h-9"
                                            >
                                                <Link
                                                    href={
                                                        pages.edit(page.id).url
                                                    }
                                                >
                                                    Edit
                                                </Link>
                                            </Button>
                                            <Button
                                                asChild
                                                size="sm"
                                                variant="ghost"
                                                className="min-h-9"
                                            >
                                                <a
                                                    href={
                                                        pages.preview(page.id)
                                                            .url
                                                    }
                                                    target="_blank"
                                                    rel="noreferrer"
                                                >
                                                    <ExternalLink aria-hidden="true" />
                                                    Preview
                                                </a>
                                            </Button>
                                            {page.state !== 'published' && (
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    className="min-h-9"
                                                    onClick={() =>
                                                        publish(
                                                            page.id,
                                                            page.title,
                                                        )
                                                    }
                                                >
                                                    Publish
                                                </Button>
                                            )}
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="destructive"
                                                className="min-h-9"
                                                onClick={() =>
                                                    destroy(page.id, page.title)
                                                }
                                            >
                                                <Trash2 aria-hidden="true" />
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    {paginator.data.length === 0 && (
                        <p className="p-8 text-center text-sm text-muted-foreground">
                            No custom pages match these filters.
                        </p>
                    )}
                </div>

                <div className="grid gap-3 lg:hidden">
                    {paginator.data.map((page) => (
                        <Card key={page.id}>
                            <CardHeader className="gap-2">
                                <div className="flex items-start justify-between gap-2">
                                    <div className="min-w-0">
                                        <CardTitle className="truncate text-base">
                                            {page.title}
                                        </CardTitle>
                                        <CardDescription className="truncate">
                                            /content/{page.slug}
                                        </CardDescription>
                                    </div>
                                    <Badge
                                        variant={
                                            page.state === 'published'
                                                ? 'secondary'
                                                : 'outline'
                                        }
                                    >
                                        {page.state}
                                    </Badge>
                                </div>
                            </CardHeader>
                            <CardContent className="grid gap-2 text-sm">
                                <p className="text-xs text-muted-foreground">
                                    Updated {formatAdminDate(page.updatedAt)}
                                </p>
                                <div className="flex flex-wrap gap-2">
                                    <Button
                                        asChild
                                        size="sm"
                                        variant="outline"
                                        className="min-h-11"
                                    >
                                        <Link href={pages.edit(page.id).url}>
                                            Edit
                                        </Link>
                                    </Button>
                                    <Button
                                        asChild
                                        size="sm"
                                        variant="outline"
                                        className="min-h-11"
                                    >
                                        <a
                                            href={pages.preview(page.id).url}
                                            target="_blank"
                                            rel="noreferrer"
                                        >
                                            Preview
                                        </a>
                                    </Button>
                                    {page.state !== 'published' && (
                                        <Button
                                            type="button"
                                            size="sm"
                                            className="min-h-11"
                                            onClick={() =>
                                                publish(page.id, page.title)
                                            }
                                        >
                                            Publish
                                        </Button>
                                    )}
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="destructive"
                                        className="min-h-11"
                                        onClick={() =>
                                            destroy(page.id, page.title)
                                        }
                                    >
                                        Delete
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                    {paginator.data.length === 0 && (
                        <Card>
                            <CardContent className="p-8 text-center text-sm text-muted-foreground">
                                No custom pages match these filters.
                            </CardContent>
                        </Card>
                    )}
                </div>

                <footer className="flex flex-col gap-3 text-sm text-muted-foreground sm:flex-row sm:items-center sm:justify-between">
                    <p>
                        Showing {paginator.from ?? 0}–{paginator.to ?? 0} of{' '}
                        {paginator.total.toLocaleString()} pages
                    </p>
                    {paginator.last_page > 1 && (
                        <nav className="flex flex-wrap gap-1">
                            {paginator.links.map((link) =>
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

            <AdminConfirmDialog
                confirmation={pending?.confirmation ?? null}
                processing={false}
                onCancel={() => setPending(null)}
                onConfirm={() => pending?.run()}
            />
        </>
    );
}
