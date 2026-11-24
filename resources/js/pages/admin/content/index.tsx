import { Head, Link, router, usePage } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';
import { Eye, Pencil, Search, Trash2 } from 'lucide-react';
import { AdminContentDeleteDialog } from '@/components/admin/admin-content-delete-dialog';
import { ContentEditorRoutesProvider } from '@/components/admin/content-editor-routes';
import { CustomDomainEditor } from '@/components/admin/custom-domain-editor';
import { formatAdminDate } from '@/components/admin/format';
import { FolderFormDialog } from '@/components/folders/folder-form-dialog';
import { LinkFormDialog } from '@/components/links/link-form-dialog';
import { EditOverlayDialog } from '@/components/overlays/overlay-form-dialogs';
import { PageHeader } from '@/components/page-header';
import { QrCodeFormDialog } from '@/components/qr-codes/qr-code-form-dialog';
import { EditPixelDialog } from '@/components/tracking-pixels/pixel-editor';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { toLinkListItem } from '@/types/links';
import type { AdminContentPageProps } from '@/types/admin-content';

const singularLabels: Record<string, string> = {
    links: 'short link',
    biolinks: 'bio page',
    'qr-codes': 'QR code',
    folders: 'folder',
    'custom-domains': 'custom domain',
    'link-overlays': 'CTA overlay',
    'link-pages': 'link page',
    pixels: 'tracking pixel',
};

function deleteDescription(key: string, name: string): string {
    switch (key) {
        case 'links':
            return `This permanently removes ${name}, its click history, and its linked QR image. Independent QR campaigns keep their scan history.`;
        case 'biolinks':
            return `This permanently removes ${name}, its link buttons, content blocks, and uploaded images.`;
        case 'qr-codes':
            return `This removes ${name} and its scan history. The linked short link, if any, remains.`;
        case 'folders':
            return `This removes ${name}. Links in it remain and become unfiled.`;
        case 'custom-domains':
            return `This disconnects ${name} from every short link. Those links use the default LinkPilot host again.`;
        case 'link-overlays':
            return `This removes ${name}. Links that used it remain and no longer show the overlay.`;
        case 'link-pages':
            return `This permanently removes ${name}, its blocks, items, variants, form responses, and uploaded files.`;
        case 'pixels':
            return `This removes ${name} and its rules. Tracked pages keep working without the pixel.`;
        default:
            return `This permanently removes ${name}. This cannot be undone.`;
    }
}

export default function AdminContentIndex({
    resource,
    rows,
    filters,
    editing,
}: AdminContentPageProps) {
    const { url } = usePage();
    const path = url.split('?')[0];
    const listQuery = url.includes('?') ? url.slice(url.indexOf('?') + 1) : '';
    const inlineEditor = !['biolinks', 'link-pages'].includes(resource.key);
    const [query, setQuery] = useState(filters.q ?? '');
    const [state, setState] = useState(filters.state ?? 'any');
    const [sort, setSort] = useState(filters.sort);
    const [direction, setDirection] = useState(filters.direction);

    function closeEditor(open: boolean) {
        if (open || !editing) {
            return;
        }

        const editPath = `${editing.backUrl.split('?')[0]}/${editing.record.id}`;
        if (!window.location.pathname.endsWith(editPath)) {
            return;
        }

        router.get(editing.backUrl, {}, {
            preserveScroll: true,
            preserveState: true,
            only: ['editing'],
        });
    }

    function editHref(editUrl: string): string {
        return listQuery === '' ? editUrl : `${editUrl}?${listQuery}`;
    }

    function viewHref(viewUrl: string): string {
        return listQuery === '' ? viewUrl : `${viewUrl}?${listQuery}`;
    }

    function deleteHref(deleteUrl: string): string {
        return listQuery === '' ? deleteUrl : `${deleteUrl}?${listQuery}`;
    }

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
            <div className="mx-auto flex w-full max-w-7xl min-w-0 flex-1 flex-col gap-5 px-5 pt-4 pb-10">
                <PageHeader
                    title={resource.title}
                    description={`${resource.description} Editors use the selected workspace without adding an administrator membership.`}
                />

                <form
                    onSubmit={apply}
                    className="flex flex-wrap items-center gap-2 rounded-xl border bg-card p-3"
                >
                    <div className="relative w-full min-w-0 sm:max-w-sm sm:flex-1">
                        <Search
                            className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <Input
                            id="content-search"
                            value={query}
                            maxLength={100}
                            placeholder={`Search ${resource.title.toLowerCase()}`}
                            aria-label={`Search ${resource.title.toLowerCase()}`}
                            className="h-9 pl-8"
                            onChange={(event) => setQuery(event.target.value)}
                        />
                    </div>
                    <Select value={state} onValueChange={setState}>
                        <SelectTrigger
                            id="content-state"
                            aria-label="State"
                            className="h-9 w-auto min-w-32"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="any">Any state</SelectItem>
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
                    <Select value={sort} onValueChange={setSort}>
                        <SelectTrigger
                            id="content-sort"
                            aria-label="Sort by"
                            className="h-9 w-auto min-w-28"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="updated_at">Updated</SelectItem>
                            <SelectItem value="created_at">Created</SelectItem>
                            <SelectItem value="name">Name</SelectItem>
                        </SelectContent>
                    </Select>
                    <Select value={direction} onValueChange={setDirection}>
                        <SelectTrigger
                            id="content-direction"
                            aria-label="Direction"
                            className="h-9 w-auto min-w-28"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="desc">Descending</SelectItem>
                            <SelectItem value="asc">Ascending</SelectItem>
                        </SelectContent>
                    </Select>
                    <Button type="submit" size="sm" className="ml-auto">
                        Apply
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={reset}
                    >
                        Reset
                    </Button>
                </form>

                <div className="lp-table-shell hidden lg:block">
                    <table
                        data-table-surface={
                            rows.data.length > 0 ? 'populated' : undefined
                        }
                        className="w-full border-collapse text-sm"
                    >
                        <thead className="border-b bg-muted/40 text-left text-xs text-muted-foreground">
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
                                <th className="px-4 py-3 font-medium">
                                    <span className="sr-only">Actions</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.data.map((row) => (
                                <tr
                                    key={`${row.workspace}-${row.name}-${row.identifier}-${row.createdAt}`}
                                    className="border-t transition-colors hover:bg-muted/30"
                                >
                                    <td className="max-w-72 px-4 py-3">
                                        {row.viewUrl ? (
                                            <Link
                                                href={viewHref(row.viewUrl)}
                                                preserveScroll
                                                className="block truncate font-medium underline-offset-4 hover:underline"
                                            >
                                                {row.name}
                                            </Link>
                                        ) : (
                                            <p className="truncate font-medium">
                                                {row.name}
                                            </p>
                                        )}
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
                                        <span className="text-xs text-muted-foreground">
                                            {row.state}
                                        </span>
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
                                                        <span className="tabular-nums">
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
                                    <td className="px-4 py-3 text-right">
                                        <div className="flex justify-end gap-1">
                                            {row.editUrl && (
                                                <Button
                                                    asChild
                                                    size="icon"
                                                    variant="secondary"
                                                    className="size-8 rounded-full"
                                                    title={`Edit ${row.name}`}
                                                >
                                                    <Link
                                                        href={editHref(row.editUrl)}
                                                        preserveScroll
                                                        preserveState={inlineEditor}
                                                        only={
                                                            inlineEditor
                                                                ? ['editing']
                                                                : undefined
                                                        }
                                                        aria-label={`Edit ${row.name}`}
                                                    >
                                                        <Pencil aria-hidden="true" />
                                                    </Link>
                                                </Button>
                                            )}
                                            {row.deleteUrl && (
                                                <AdminContentDeleteDialog
                                                    deleteUrl={deleteHref(row.deleteUrl)}
                                                    resourceLabel={
                                                        singularLabels[resource.key] ?? 'record'
                                                    }
                                                    recordName={row.name}
                                                    description={deleteDescription(
                                                        resource.key,
                                                        row.name,
                                                    )}
                                                    confirmationValue={
                                                        row.deleteConfirmation
                                                    }
                                                >
                                                    <Button
                                                        type="button"
                                                        size="icon"
                                                        variant="destructive"
                                                        className="size-8 rounded-full"
                                                        title={`Delete ${row.name}`}
                                                        aria-label={`Delete ${row.name}`}
                                                    >
                                                        <Trash2 aria-hidden="true" />
                                                    </Button>
                                                </AdminContentDeleteDialog>
                                            )}
                                        </div>
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
                                            {row.viewUrl ? (
                                                <Link
                                                    href={viewHref(row.viewUrl)}
                                                    preserveScroll
                                                    className="underline-offset-4 hover:underline"
                                                >
                                                    {row.name}
                                                </Link>
                                            ) : (
                                                row.name
                                            )}
                                        </CardTitle>
                                        {row.identifier && (
                                            <CardDescription className="truncate">
                                                {row.identifier}
                                            </CardDescription>
                                        )}
                                    </div>
                                    <span className="text-xs text-muted-foreground">
                                        {row.state}
                                    </span>
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
                                <div className="flex flex-wrap gap-2">
                                    {row.viewUrl && (
                                        <Button
                                            asChild
                                            size="sm"
                                            className="w-fit"
                                        >
                                            <Link
                                                href={viewHref(row.viewUrl)}
                                                preserveScroll
                                                aria-label={`Open ${row.name}`}
                                            >
                                                <Eye aria-hidden="true" />
                                                Open
                                            </Link>
                                        </Button>
                                    )}
                                    {row.editUrl && (
                                        <Button asChild variant="outline" size="sm" className="w-fit">
                                            <Link
                                                href={editHref(row.editUrl)}
                                                preserveScroll
                                                preserveState={inlineEditor}
                                                only={
                                                    inlineEditor
                                                        ? ['editing']
                                                        : undefined
                                                }
                                                aria-label={`Edit ${row.name}`}
                                            >
                                                <Pencil aria-hidden="true" />
                                                Edit
                                            </Link>
                                        </Button>
                                    )}
                                    {row.deleteUrl && (
                                        <AdminContentDeleteDialog
                                            deleteUrl={deleteHref(row.deleteUrl)}
                                            resourceLabel={
                                                singularLabels[resource.key] ?? 'record'
                                            }
                                            recordName={row.name}
                                            description={deleteDescription(
                                                resource.key,
                                                row.name,
                                            )}
                                            confirmationValue={row.deleteConfirmation}
                                        >
                                            <Button
                                                type="button"
                                                variant="destructive"
                                                size="sm"
                                                className="w-fit"
                                                aria-label={`Delete ${row.name}`}
                                            >
                                                <Trash2 aria-hidden="true" />
                                                Delete
                                            </Button>
                                        </AdminContentDeleteDialog>
                                    )}
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                    {rows.data.length === 0 && (
                        <Card variant="transparent">
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
                        <nav
                            className="flex flex-wrap gap-1"
                            aria-label="Content pagination"
                        >
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

            {editing && (
                <ContentEditorRoutesProvider
                    ownerPrefix={`/dashboard/${editing.resource}/${editing.record.id}`}
                    adminPrefix={`/admin/${editing.resource}/${editing.record.id}`}
                    backUrl={editing.backUrl}
                >
                    {editing.resource === 'links' && (
                        <LinkFormDialog
                            link={toLinkListItem(editing.record)}
                            folders={editing.folders}
                            customDomains={editing.customDomains}
                            linkOverlays={editing.linkOverlays}
                            linkPages={editing.linkPages}
                            tagOptions={editing.tagOptions}
                            countries={editing.countries}
                            canManageApprovals={editing.canManageApprovals}
                            listQuery={listQuery}
                            open
                            onOpenChange={closeEditor}
                        />
                    )}
                    {editing.resource === 'qr-codes' && (
                        <QrCodeFormDialog
                            campaign={editing.record}
                            availableLinks={editing.availableLinks}
                            open
                            onOpenChange={closeEditor}
                        />
                    )}
                    {editing.resource === 'folders' && (
                        <FolderFormDialog
                            folder={editing.record}
                            listQuery={listQuery}
                            open
                            onOpenChange={closeEditor}
                        />
                    )}
                    {editing.resource === 'custom-domains' && (
                        <CustomDomainEditor
                            domain={editing.record}
                            onClose={() => closeEditor(false)}
                        />
                    )}
                    {editing.resource === 'link-overlays' && (
                        <EditOverlayDialog
                            overlay={editing.record}
                            open
                            onOpenChange={closeEditor}
                        />
                    )}
                    {editing.resource === 'pixels' && (
                        <EditPixelDialog
                            pixel={editing.record}
                            targets={editing.availableTargets}
                            open
                            onOpenChange={closeEditor}
                        />
                    )}
                </ContentEditorRoutesProvider>
            )}
        </>
    );
}
