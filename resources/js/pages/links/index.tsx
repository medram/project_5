import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    Activity,
    BarChart3,
    CalendarClock,
    Download,
    ExternalLink,
    Folder,
    KeyRound,
    Link2,
    MousePointerClick,
    Pause,
    Pencil,
    Play,
    Plus,
    Trash2,
    Upload,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { PageHeader } from '@/components/page-header';
import { ResourceListBulkTray } from '@/components/resource-list/bulk-tray';
import { ResourceListFilterChips } from '@/components/resource-list/filter-chips';
import { CreateLinkTagDialog } from '@/components/links/create-link-tag-dialog';
import { LinkFormDialog } from '@/components/links/link-form-dialog';
import { ResourceListPagination } from '@/components/resource-list/pagination';
import { ResourceListSortMenu } from '@/components/resource-list/sort-menu';
import { ResourceListToolbar } from '@/components/resource-list/toolbar';
import { ResourceListViewToggle } from '@/components/resource-list/view-toggle';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Field } from '@/components/ui/field';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import {
    Empty,
    EmptyContent,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import { Spinner } from '@/components/ui/spinner';
import { destroy, index, insights } from '@/routes/links';
import type { CustomDomainOption } from '@/types/custom-domains';
import type { FolderOption } from '@/types/folders';
import {
    type LinkIndexProps,
    type LinkListItem,
    type LinkTagOption,
    toLinkListItem,
    type LinkPageOption,
} from '@/types/links';
import type { LinkOverlayOption } from '@/types/overlays';

function withListQuery(url: string, listQuery: string): string {
    return listQuery === '' ? url : `${url}?${listQuery}`;
}

function withoutListFilter(
    url: string,
    listQuery: string,
    key: string,
): string {
    const query = new URLSearchParams(listQuery);
    query.delete(key);
    query.delete('page');
    return withListQuery(url, query.toString());
}

function withListView(
    url: string,
    listQuery: string,
    view: 'cards' | 'table',
): string {
    const query = new URLSearchParams(listQuery);
    query.delete('page');
    query.set('view', view);
    return withListQuery(url, query.toString());
}

function DeleteLinkDialog({
    link,
    listQuery,
}: {
    link: LinkListItem;
    listQuery: string;
}) {
    const [open, setOpen] = useState(false);
    const [processing, setProcessing] = useState(false);

    function deleteLink() {
        router.delete(withListQuery(destroy(link.id).url, listQuery), {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onSuccess: () => setOpen(false),
            onFinish: () => setProcessing(false),
        });
    }

    return (
        <Dialog
            open={open}
            onOpenChange={(nextOpen) => {
                if (!processing) {
                    setOpen(nextOpen);
                }
            }}
        >
            <DialogTrigger asChild>
                <Button variant="destructive" size="sm">
                    <Trash2 data-icon="inline-start" />
                    Delete
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete this short link?</DialogTitle>
                    <DialogDescription>
                        /{link.slug} will stop working immediately. Its click
                        history will also be removed.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <DialogClose asChild>
                        <Button
                            type="button"
                            variant="outline"
                            disabled={processing}
                        >
                            Cancel
                        </Button>
                    </DialogClose>
                    <Button
                        type="button"
                        variant="destructive"
                        onClick={deleteLink}
                        disabled={processing}
                    >
                        {processing && <Spinner data-icon="inline-start" />}
                        Delete link
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function DeleteSelectedLinksDialog({
    linkIds,
    listQuery,
    onDeleted,
}: {
    linkIds: number[];
    listQuery: string;
    onDeleted: () => void;
}) {
    const [open, setOpen] = useState(false);
    const [processing, setProcessing] = useState(false);

    function deleteSelectedLinks() {
        router.patch(
            withListQuery('/dashboard/links/bulk', listQuery),
            { action: 'delete', ids: linkIds },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onSuccess: () => {
                    setOpen(false);
                    onDeleted();
                },
                onFinish: () => setProcessing(false),
            },
        );
    }

    return (
        <Dialog
            open={open}
            onOpenChange={(nextOpen) => {
                if (!processing) setOpen(nextOpen);
            }}
        >
            <DialogTrigger asChild>
                <Button
                    type="button"
                    variant="destructive"
                    className="min-h-11"
                >
                    <Trash2 data-icon="inline-start" />
                    Delete selected
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete selected short links?</DialogTitle>
                    <DialogDescription>
                        {linkIds.length} links will stop working immediately.
                        Their click history will also be removed.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <DialogClose asChild>
                        <Button
                            type="button"
                            variant="outline"
                            disabled={processing}
                        >
                            Cancel
                        </Button>
                    </DialogClose>
                    <Button
                        type="button"
                        variant="destructive"
                        onClick={deleteSelectedLinks}
                        disabled={processing}
                    >
                        {processing && <Spinner data-icon="inline-start" />}
                        Delete links
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function LinkActions({
    link,
    folders,
    customDomains,
    linkOverlays,
    linkPages,
    canManageApprovals,
    tagOptions,
    listQuery,
}: {
    link: LinkListItem;
    folders: FolderOption[];
    customDomains: CustomDomainOption[];
    linkOverlays: LinkOverlayOption[];
    linkPages: LinkPageOption[];
    canManageApprovals: boolean;
    tagOptions: LinkTagOption[];
    listQuery: string;
}) {
    const [updatingStatus, setUpdatingStatus] = useState(false);

    function toggleStatus() {
        router.patch(
            withListQuery('/dashboard/links/bulk', listQuery),
            {
                action: link.isActive ? 'deactivate' : 'activate',
                ids: [link.id],
            },
            {
                preserveScroll: true,
                onStart: () => setUpdatingStatus(true),
                onFinish: () => setUpdatingStatus(false),
            },
        );
    }

    function runBulkAction(action: 'archive' | 'restore') {
        router.patch(
            withListQuery('/dashboard/links/bulk', listQuery),
            { action, ids: [link.id] },
            {
                preserveScroll: true,
                onStart: () => setUpdatingStatus(true),
                onFinish: () => setUpdatingStatus(false),
            },
        );
    }

    return (
        <div className="flex flex-wrap gap-2 [&_a]:min-h-11 [&_a]:min-w-11 [&_button]:min-h-11 [&_button]:min-w-11">
            <LinkFormDialog
                link={link}
                folders={folders}
                customDomains={customDomains}
                linkOverlays={linkOverlays}
                linkPages={linkPages}
                canManageApprovals={canManageApprovals}
                tagOptions={tagOptions}
                listQuery={listQuery}
            >
                <Button variant="outline" size="sm">
                    <Pencil data-icon="inline-start" />
                    Edit
                </Button>
            </LinkFormDialog>
            {!link.archivedAt ? (
                <>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={toggleStatus}
                        disabled={updatingStatus}
                    >
                        {updatingStatus ? (
                            <Spinner data-icon="inline-start" />
                        ) : link.isActive ? (
                            <Pause data-icon="inline-start" />
                        ) : (
                            <Play data-icon="inline-start" />
                        )}
                        {link.isActive ? 'Pause' : 'Activate'}
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() => runBulkAction('archive')}
                        disabled={updatingStatus}
                    >
                        Archive
                    </Button>
                </>
            ) : (
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={() => runBulkAction('restore')}
                    disabled={updatingStatus}
                >
                    {updatingStatus && <Spinner data-icon="inline-start" />}
                    Restore
                </Button>
            )}
            <Button variant="outline" size="sm" asChild>
                <Link href={insights(link.id)}>
                    <BarChart3 data-icon="inline-start" />
                    Insights
                </Link>
            </Button>
            <Button variant="outline" size="sm" asChild>
                <Link
                    href={withListQuery(
                        `/dashboard/links/${link.id}/revisions`,
                        listQuery,
                    )}
                >
                    Revision history
                </Link>
            </Button>
            <DeleteLinkDialog link={link} listQuery={listQuery} />
        </div>
    );
}

function LinkRow({
    link,
    folders,
    customDomains,
    linkOverlays,
    linkPages,
    canManageApprovals,
    tagOptions,
    listQuery,
    selected,
    onToggle,
}: {
    link: LinkListItem;
    folders: FolderOption[];
    customDomains: CustomDomainOption[];
    linkOverlays: LinkOverlayOption[];
    linkPages: LinkPageOption[];
    canManageApprovals: boolean;
    tagOptions: LinkTagOption[];
    listQuery: string;
    selected: boolean;
    onToggle: () => void;
}) {
    const shortLinkUrl = link.customDomain
        ? `https://${link.customDomain.hostname}/${link.slug}`
        : `/${link.slug}`;
    const shortLinkLabel = link.customDomain
        ? `${link.customDomain.hostname}/${link.slug}`
        : `/${link.slug}`;
    const statusLabel = link.archivedAt
        ? 'Archived'
        : link.approvalStatus === 'pending'
          ? 'Pending review'
          : link.isActive
            ? 'Live'
            : 'Paused';
    const targetLabel =
        link.deliveryMode === 'link_page'
            ? `Link page: ${link.linkPage?.name ?? 'Unavailable'}`
            : (link.destinationUrl ?? 'No destination');

    return (
        <Card>
            <CardHeader className="gap-4">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div className="flex min-w-0 flex-1 gap-3">
                        <label className="inline-flex min-h-11 min-w-11 shrink-0 items-center justify-center">
                            <input
                                type="checkbox"
                                checked={selected}
                                onChange={onToggle}
                                aria-label={`Select ${link.title || `/${link.slug}`}`}
                                className="size-4 accent-primary focus-visible:ring-2 focus-visible:ring-ring"
                            />
                        </label>
                        <div className="flex min-w-0 flex-col gap-1">
                            <div className="flex flex-wrap items-center gap-2">
                                <CardTitle>
                                    {link.title || 'Untitled route'}
                                </CardTitle>
                                <Badge
                                    variant={
                                        link.archivedAt ||
                                        (!link.isActive &&
                                            link.approvalStatus !== 'pending')
                                            ? 'outline'
                                            : link.approvalStatus === 'pending'
                                              ? 'secondary'
                                              : 'default'
                                    }
                                >
                                    {statusLabel}
                                </Badge>
                                {!link.archivedAt &&
                                    link.approvalStatus === 'approved' &&
                                    link.hasPendingRevision && (
                                        <Badge variant="secondary">
                                            Pending changes
                                        </Badge>
                                    )}
                                {link.tags.map((tag) => (
                                    <Badge
                                        key={tag.id}
                                        variant="outline"
                                        style={
                                            tag.color
                                                ? { borderColor: tag.color }
                                                : undefined
                                        }
                                    >
                                        {tag.name}
                                    </Badge>
                                ))}
                            </div>
                            <CardDescription className="truncate">
                                {targetLabel}
                            </CardDescription>
                        </div>
                    </div>
                    <a
                        href={shortLinkUrl}
                        target="_blank"
                        rel="noreferrer"
                        className="inline-flex min-h-11 max-w-full min-w-0 items-center gap-1 truncate text-sm font-medium text-primary underline-offset-4 hover:underline"
                    >
                        {shortLinkLabel}
                        <ExternalLink aria-hidden="true" />
                        <span className="sr-only">Open short link</span>
                    </a>
                </div>
            </CardHeader>
            <CardContent className="flex flex-col gap-4">
                <Separator />
                <dl className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div className="flex min-w-0 flex-col gap-1">
                        <dt className="text-sm text-muted-foreground">
                            Destination
                        </dt>
                        <dd className="min-w-0 truncate text-sm font-medium">
                            {link.deliveryMode === 'link_page' ? (
                                (link.linkPage?.name ?? 'Unavailable page')
                            ) : link.destinationUrl ? (
                                <a
                                    href={link.destinationUrl}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="inline-flex min-h-11 max-w-full min-w-0 items-center underline-offset-4 hover:underline"
                                >
                                    <span className="min-w-0 truncate">
                                        {link.destinationUrl}
                                    </span>
                                </a>
                            ) : (
                                'No destination'
                            )}
                        </dd>
                    </div>
                    <div className="flex flex-col gap-1">
                        <dt className="text-sm text-muted-foreground">
                            Clicks
                        </dt>
                        <dd className="flex items-center gap-2 text-sm font-medium tabular-nums">
                            <MousePointerClick aria-hidden="true" />
                            {link.clickCount}
                            {link.maxClicks !== null &&
                                ` / ${link.maxClicks}`}{' '}
                            {link.clickCount === 1 ? 'click' : 'clicks'}
                        </dd>
                    </div>
                    <div className="flex flex-col gap-1">
                        <dt className="text-sm text-muted-foreground">
                            Folder
                        </dt>
                        <dd className="flex items-center gap-2 text-sm font-medium">
                            <Folder aria-hidden="true" />
                            {link.folder?.name ?? 'No folder'}
                        </dd>
                    </div>
                    {(link.availableFrom || link.expiresAt) && (
                        <div className="flex flex-col gap-1">
                            <dt className="text-sm text-muted-foreground">
                                Availability
                            </dt>
                            <dd className="flex items-start gap-2 text-sm font-medium">
                                <CalendarClock
                                    className="mt-0.5 shrink-0"
                                    aria-hidden="true"
                                />
                                <span>
                                    {link.availableFrom && (
                                        <span className="block">
                                            Starts{' '}
                                            {new Date(
                                                link.availableFrom,
                                            ).toLocaleString()}
                                        </span>
                                    )}
                                    {link.expiresAt && (
                                        <span className="block">
                                            Expires{' '}
                                            {new Date(
                                                link.expiresAt,
                                            ).toLocaleString()}
                                        </span>
                                    )}
                                </span>
                            </dd>
                        </div>
                    )}
                    {link.hasAccessCode && (
                        <div className="flex flex-col gap-1">
                            <dt className="text-sm text-muted-foreground">
                                Access
                            </dt>
                            <dd className="flex items-center gap-2 text-sm font-medium">
                                <KeyRound aria-hidden="true" />
                                Access code required
                            </dd>
                        </div>
                    )}
                </dl>
            </CardContent>
            <CardFooter className="flex-wrap gap-2">
                <LinkActions
                    link={link}
                    folders={folders}
                    customDomains={customDomains}
                    linkOverlays={linkOverlays}
                    linkPages={linkPages}
                    canManageApprovals={canManageApprovals}
                    tagOptions={tagOptions}
                    listQuery={listQuery}
                />
            </CardFooter>
        </Card>
    );
}

function listStateQuery(filters: LinkIndexProps['filters']): string {
    const query = new URLSearchParams();
    for (const [key, value] of Object.entries(filters)) {
        if (value !== '') query.set(key, value);
    }
    return query.toString();
}

export default function ShortLinks({
    links: linkPage,
    folders,
    customDomains,
    linkOverlays,
    linkPages,
    tagOptions,
    canManageApprovals,
    filters,
}: LinkIndexProps) {
    const links = linkPage.data.map(toLinkListItem);
    const listQuery = listStateQuery(filters);
    const importForm = useForm<{ file: File | null }>({ file: null });
    const [selectedIds, setSelectedIds] = useState<number[]>([]);
    useEffect(() => {
        const visibleIds = new Set(linkPage.data.map((link) => link.id));
        setSelectedIds((current) => current.filter((id) => visibleIds.has(id)));
    }, [linkPage.data]);
    const selectedLinks = links.filter((link) => selectedIds.includes(link.id));
    const allSelected =
        links.length > 0 && selectedLinks.length === links.length;

    function toggleSelectedLink(id: number) {
        setSelectedIds((current) =>
            current.includes(id)
                ? current.filter((selectedId) => selectedId !== id)
                : [...current, id],
        );
    }

    function bulkAction(
        action: 'archive' | 'restore' | 'activate' | 'deactivate',
    ) {
        router.patch(
            withListQuery('/dashboard/links/bulk', listQuery),
            { action, ids: selectedLinks.map((link) => link.id) },
            {
                preserveScroll: true,
                onSuccess: () => setSelectedIds([]),
            },
        );
    }

    return (
        <>
            <Head title="Short links" />

            <section className="flex max-w-full min-w-0 flex-1 flex-col gap-6 overflow-x-hidden p-4 md:p-6">
                <PageHeader
                    title="Short links"
                    eyebrow="Routing desk"
                    icon={Link2}
                    description="Create compact routes, control when they resolve, and inspect the traffic they receive."
                    actions={
                        <div className="flex flex-wrap gap-2">
                            <Button
                                variant="outline"
                                className="min-h-11 min-w-11"
                                asChild
                            >
                                <a
                                    href={withListQuery(
                                        '/dashboard/links/export',
                                        listQuery,
                                    )}
                                >
                                    <Download data-icon="inline-start" />
                                    Export CSV
                                </a>
                            </Button>
                            <CreateLinkTagDialog
                                tags={tagOptions}
                                listQuery={listQuery}
                            >
                                <Button
                                    variant="outline"
                                    className="min-h-11 min-w-11"
                                >
                                    Create tag
                                </Button>
                            </CreateLinkTagDialog>
                            <LinkFormDialog
                                folders={folders}
                                customDomains={customDomains}
                                linkPages={linkPages}
                                linkOverlays={linkOverlays}
                                tagOptions={tagOptions}
                                canManageApprovals={canManageApprovals}
                                listQuery={listQuery}
                            >
                                <Button className="min-h-11 min-w-11">
                                    <Plus data-icon="inline-start" />
                                    Create short link
                                </Button>
                            </LinkFormDialog>
                        </div>
                    }
                />
                <ResourceListToolbar
                    key={listQuery}
                    action={index().url}
                    className="grid min-w-0 gap-3 rounded-lg border bg-card p-4 sm:grid-cols-2 xl:grid-cols-7 [&_button]:min-h-11 [&_button]:min-w-11"
                >
                    <input type="hidden" name="view" value={filters.view} />
                    <Field className="sm:col-span-2">
                        <Input
                            className="min-h-11 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            name="search"
                            defaultValue={filters.search}
                            placeholder="Search title or short code"
                            aria-label="Search short links"
                        />
                    </Field>
                    <label className="flex flex-col gap-1 text-sm">
                        <span>Folder</span>
                        <select
                            name="folder"
                            defaultValue={filters.folder}
                            className="min-h-11 rounded-md border bg-background px-2 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        >
                            <option value="">All folders</option>
                            {folders.map((folder) => (
                                <option key={folder.id} value={folder.id}>
                                    {folder.name}
                                </option>
                            ))}
                        </select>
                    </label>
                    <label className="flex flex-col gap-1 text-sm">
                        <span>Tag</span>
                        <select
                            name="tag"
                            defaultValue={filters.tag}
                            className="min-h-11 rounded-md border bg-background px-2 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        >
                            <option value="">All tags</option>
                            {tagOptions.map((tag) => (
                                <option key={tag.id} value={tag.id}>
                                    {tag.name}
                                </option>
                            ))}
                        </select>
                    </label>
                    <label className="flex flex-col gap-1 text-sm">
                        <span>Status</span>
                        <select
                            name="status"
                            defaultValue={filters.status}
                            className="min-h-11 rounded-md border bg-background px-2 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        >
                            <option value="">All statuses</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="archived">Archived</option>
                            <option value="pending">Pending review</option>
                        </select>
                    </label>
                    <ResourceListSortMenu
                        value={filters.sort}
                        options={[
                            { value: 'created_at', label: 'Created' },
                            { value: 'title', label: 'Title' },
                            { value: 'clicks_count', label: 'Clicks' },
                        ]}
                    />
                    <label className="flex flex-col gap-1 text-sm">
                        <span>Direction</span>
                        <select
                            name="direction"
                            defaultValue={filters.direction}
                            className="min-h-11 rounded-md border bg-background px-2 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        >
                            <option value="desc">Descending</option>
                            <option value="asc">Ascending</option>
                        </select>
                    </label>
                    <div className="flex items-end gap-2 sm:col-span-2 xl:col-span-7">
                        <Button type="submit" variant="outline">
                            Apply filters
                        </Button>
                        <Button
                            variant="ghost"
                            asChild
                            className="min-h-11 min-w-11"
                        >
                            <Link href={index()}>Reset</Link>
                        </Button>
                    </div>
                </ResourceListToolbar>
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        const query = new URLSearchParams(listQuery);
                        importForm.post(
                            withListQuery(
                                '/dashboard/links/import',
                                query.toString(),
                            ),
                            { forceFormData: true },
                        );
                    }}
                    className="grid min-w-0 gap-3 rounded-lg border bg-card p-4 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end"
                >
                    <label
                        htmlFor="short-link-csv"
                        className="flex min-w-0 flex-col gap-1 text-sm"
                    >
                        <span className="font-medium">Import short links</span>
                        <span
                            id="short-link-csv-description"
                            className="text-muted-foreground"
                        >
                            CSV only, up to 1 MB. Exported files never include
                            access codes.
                        </span>
                        <Input
                            id="short-link-csv"
                            type="file"
                            accept=".csv,text/csv"
                            className="min-h-11 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            aria-invalid={Boolean(importForm.errors.file)}
                            aria-describedby={`short-link-csv-description${importForm.errors.file ? ' short-link-csv-error' : ''}`}
                            onChange={(event) =>
                                importForm.setData(
                                    'file',
                                    event.currentTarget.files?.[0] ?? null,
                                )
                            }
                        />
                        {importForm.errors.file && (
                            <span
                                id="short-link-csv-error"
                                role="alert"
                                className="text-sm text-destructive"
                            >
                                {importForm.errors.file}
                            </span>
                        )}
                    </label>
                    <Button
                        type="submit"
                        disabled={
                            !importForm.data.file || importForm.processing
                        }
                        className="min-h-11"
                    >
                        {importForm.processing ? (
                            <Spinner data-icon="inline-start" />
                        ) : (
                            <Upload data-icon="inline-start" />
                        )}
                        Import CSV
                    </Button>
                </form>
                <ResourceListFilterChips
                    filters={[
                        ...(filters.search
                            ? [
                                  {
                                      key: 'search',
                                      label: 'Search',
                                      value: filters.search,
                                      href: withoutListFilter(
                                          index().url,
                                          listQuery,
                                          'search',
                                      ),
                                  },
                              ]
                            : []),
                        ...(filters.folder
                            ? [
                                  {
                                      key: 'folder',
                                      label: 'Folder',
                                      value:
                                          folders.find(
                                              (folder) =>
                                                  folder.id ===
                                                  Number(filters.folder),
                                          )?.name ?? filters.folder,
                                      href: withoutListFilter(
                                          index().url,
                                          listQuery,
                                          'folder',
                                      ),
                                  },
                              ]
                            : []),
                        ...(filters.tag
                            ? [
                                  {
                                      key: 'tag',
                                      label: 'Tag',
                                      value:
                                          tagOptions.find(
                                              (tag) =>
                                                  tag.id ===
                                                  Number(filters.tag),
                                          )?.name ?? filters.tag,
                                      href: withoutListFilter(
                                          index().url,
                                          listQuery,
                                          'tag',
                                      ),
                                  },
                              ]
                            : []),
                        ...(filters.status
                            ? [
                                  {
                                      key: 'status',
                                      label: 'Status',
                                      value:
                                          filters.status === 'active'
                                              ? 'Active'
                                              : filters.status === 'inactive'
                                                ? 'Inactive'
                                                : filters.status === 'archived'
                                                  ? 'Archived'
                                                  : 'Pending review',
                                      href: withoutListFilter(
                                          index().url,
                                          listQuery,
                                          'status',
                                      ),
                                  },
                              ]
                            : []),
                    ]}
                />
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <ResourceListViewToggle
                        value={filters.view}
                        cardsHref={withListView(
                            index().url,
                            listQuery,
                            'cards',
                        )}
                        tableHref={withListView(
                            index().url,
                            listQuery,
                            'table',
                        )}
                    />
                    {links.length > 0 && (
                        <label className="inline-flex min-h-11 items-center gap-2 text-sm">
                            <input
                                type="checkbox"
                                checked={allSelected}
                                onChange={() =>
                                    setSelectedIds(
                                        allSelected
                                            ? []
                                            : links.map((link) => link.id),
                                    )
                                }
                                aria-label="Select all short links on this page"
                                className="size-4 accent-primary focus-visible:ring-2 focus-visible:ring-ring"
                            />
                            Select all on this page
                        </label>
                    )}
                </div>
                <ResourceListBulkTray
                    count={selectedLinks.length}
                    singular="short link"
                    plural="short links"
                    onClear={() => setSelectedIds([])}
                >
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => bulkAction('archive')}
                    >
                        Archive
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => bulkAction('restore')}
                    >
                        Restore
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => bulkAction('activate')}
                    >
                        Activate
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => bulkAction('deactivate')}
                    >
                        Deactivate
                    </Button>
                    <DeleteSelectedLinksDialog
                        linkIds={selectedLinks.map((link) => link.id)}
                        listQuery={listQuery}
                        onDeleted={() => setSelectedIds([])}
                    />
                </ResourceListBulkTray>

                {links.length === 0 ? (
                    <Empty className="bg-muted/30">
                        <EmptyHeader className="w-full">
                            <EmptyMedia variant="icon">
                                <Activity aria-hidden="true" />
                            </EmptyMedia>
                            <EmptyTitle>
                                {filters.search ||
                                filters.folder ||
                                filters.tag ||
                                filters.status
                                    ? 'No matching short links'
                                    : 'No short links yet'}
                            </EmptyTitle>
                            <EmptyDescription className="w-full min-w-0 break-words">
                                {filters.search
                                    ? `No short links match “${filters.search}”.`
                                    : 'Add a destination to create a route you can share and measure.'}
                            </EmptyDescription>
                        </EmptyHeader>
                        <EmptyContent>
                            <LinkFormDialog
                                folders={folders}
                                customDomains={customDomains}
                                linkPages={linkPages}
                                linkOverlays={linkOverlays}
                                canManageApprovals={canManageApprovals}
                                listQuery={listQuery}
                                tagOptions={tagOptions}
                            >
                                <Button className="min-h-11 min-w-11">
                                    <Plus data-icon="inline-start" />
                                    Create a short link
                                </Button>
                            </LinkFormDialog>
                        </EmptyContent>
                    </Empty>
                ) : (
                    <>
                        {filters.view === 'table' && (
                            <div className="hidden min-w-0 overflow-hidden rounded-lg border min-[1100px]:block">
                                <table className="w-full table-fixed text-left text-sm">
                                    <thead className="bg-muted/50 text-muted-foreground">
                                        <tr>
                                            <th className="w-[4%] p-3">
                                                <span className="sr-only">
                                                    Select
                                                </span>
                                            </th>
                                            <th className="w-[14%] p-3">
                                                Link
                                            </th>
                                            <th className="w-[19%] p-3">
                                                Destination
                                            </th>
                                            <th className="w-[11%] p-3">
                                                Folder
                                            </th>
                                            <th className="w-[7%] p-3">
                                                Clicks
                                            </th>
                                            <th className="w-[9%] p-3">
                                                Status
                                            </th>
                                            <th className="w-[36%] p-3">
                                                Actions
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {links.map((link) => (
                                            <tr
                                                key={link.id}
                                                className="border-t align-top"
                                                aria-selected={selectedIds.includes(
                                                    link.id,
                                                )}
                                            >
                                                <td className="p-3">
                                                    <label className="inline-flex min-h-11 min-w-11 items-center justify-center">
                                                        <input
                                                            type="checkbox"
                                                            checked={selectedIds.includes(
                                                                link.id,
                                                            )}
                                                            onChange={() =>
                                                                toggleSelectedLink(
                                                                    link.id,
                                                                )
                                                            }
                                                            aria-label={`Select ${link.title || `/${link.slug}`}`}
                                                            className="size-4 accent-primary focus-visible:ring-2 focus-visible:ring-ring"
                                                        />
                                                    </label>
                                                </td>
                                                <td className="p-3">
                                                    <div className="truncate font-medium">
                                                        {link.title ||
                                                            'Untitled route'}
                                                    </div>
                                                    <div className="truncate text-muted-foreground">
                                                        /{link.slug}
                                                    </div>
                                                </td>
                                                <td className="p-3">
                                                    <div className="truncate">
                                                        {link.deliveryMode ===
                                                        'link_page'
                                                            ? `Link page: ${link.linkPage?.name ?? 'Unavailable'}`
                                                            : (link.destinationUrl ??
                                                              'No destination')}
                                                    </div>
                                                </td>
                                                <td className="p-3">
                                                    {link.folder?.name ??
                                                        'No folder'}
                                                </td>
                                                <td className="p-3 tabular-nums">
                                                    {link.clickCount}
                                                    {link.maxClicks !== null &&
                                                        ` / ${link.maxClicks}`}
                                                </td>
                                                <td className="p-3">
                                                    <div className="flex flex-wrap items-center gap-2">
                                                        <span>
                                                            {link.archivedAt
                                                                ? 'Archived'
                                                                : link.approvalStatus ===
                                                                    'pending'
                                                                  ? 'Pending review'
                                                                  : link.isActive
                                                                    ? 'Active'
                                                                    : 'Inactive'}
                                                        </span>
                                                        {!link.archivedAt &&
                                                            link.approvalStatus ===
                                                                'approved' &&
                                                            link.hasPendingRevision && (
                                                                <Badge variant="secondary">
                                                                    Pending
                                                                    changes
                                                                </Badge>
                                                            )}
                                                    </div>
                                                </td>
                                                <td className="p-3">
                                                    <LinkActions
                                                        link={link}
                                                        folders={folders}
                                                        customDomains={
                                                            customDomains
                                                        }
                                                        linkOverlays={
                                                            linkOverlays
                                                        }
                                                        canManageApprovals={
                                                            canManageApprovals
                                                        }
                                                        linkPages={linkPages}
                                                        listQuery={listQuery}
                                                        tagOptions={tagOptions}
                                                    />
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                        <section
                            aria-label="Short links"
                            className={`min-w-0 flex-col gap-3 ${filters.view === 'cards' ? 'flex' : 'flex min-[1100px]:hidden'}`}
                        >
                            {links.map((link) => (
                                <LinkRow
                                    key={link.id}
                                    link={link}
                                    folders={folders}
                                    customDomains={customDomains}
                                    linkOverlays={linkOverlays}
                                    canManageApprovals={canManageApprovals}
                                    linkPages={linkPages}
                                    tagOptions={tagOptions}
                                    listQuery={listQuery}
                                    selected={selectedIds.includes(link.id)}
                                    onToggle={() => toggleSelectedLink(link.id)}
                                />
                            ))}
                        </section>
                    </>
                )}
                <ResourceListPagination
                    links={linkPage.links}
                    total={linkPage.total}
                    ariaLabel="Short link pages"
                    summaryLabel="links"
                />
            </section>
        </>
    );
}

ShortLinks.layout = {
    breadcrumbs: [
        {
            title: 'Short links',
            href: index(),
        },
    ],
};
