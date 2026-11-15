import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    Activity,
    Archive,
    BarChart3,
    CalendarClock,
    Download,
    ExternalLink,
    Folder,
    Globe,
    History,
    KeyRound,
    MoreHorizontal,
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
import { ResourceListToolbar } from '@/components/resource-list/toolbar';
import { ResourceListViewToggle } from '@/components/resource-list/view-toggle';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Field, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
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
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Spinner } from '@/components/ui/spinner';
import { destroy, index, insights } from '@/routes/links';
import type { CustomDomainOption } from '@/types/custom-domains';
import type { FolderOption } from '@/types/folders';
import {
    type CountryOption,
    type LinkIndexProps,
    type LinkListItem,
    type LinkTagOption,
    toLinkListItem,
    type LinkPageOption,
} from '@/types/links';
import type { LinkOverlayOption } from '@/types/overlays';

const FILTER_ALL = '__linkpilot_all__';
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
    open,
    onOpenChange,
}: {
    link: LinkListItem;
    listQuery: string;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const [processing, setProcessing] = useState(false);

    function deleteLink() {
        router.delete(withListQuery(destroy(link.id).url, listQuery), {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onSuccess: () => onOpenChange(false),
            onFinish: () => setProcessing(false),
        });
    }

    return (
        <Dialog
            open={open}
            onOpenChange={(nextOpen) => {
                if (!processing) {
                    onOpenChange(nextOpen);
                }
            }}
        >
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
                <Button type="button" variant="outline" size="sm">
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
    countries,
    listQuery,
    onRequestDelete,
}: {
    link: LinkListItem;
    folders: FolderOption[];
    customDomains: CustomDomainOption[];
    linkOverlays: LinkOverlayOption[];
    linkPages: LinkPageOption[];
    canManageApprovals: boolean;
    tagOptions: LinkTagOption[];
    countries: CountryOption[];
    listQuery: string;
    onRequestDelete: () => void;
}) {
    const [updatingStatus, setUpdatingStatus] = useState(false);

    function runBulkAction(
        action: 'activate' | 'deactivate' | 'archive' | 'restore',
    ) {
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
        <div className="flex shrink-0 items-center gap-1 md:justify-end">
            <Button
                variant="secondary"
                size="icon"
                className="size-8 rounded-full"
                asChild
            >
                <Link
                    href={insights(link.id)}
                    title="View insights"
                    aria-label={`View insights for ${link.title || `/${link.slug}`}`}
                >
                    <BarChart3 aria-hidden="true" />
                </Link>
            </Button>
            <LinkFormDialog
                link={link}
                folders={folders}
                customDomains={customDomains}
                linkOverlays={linkOverlays}
                linkPages={linkPages}
                canManageApprovals={canManageApprovals}
                tagOptions={tagOptions}
                countries={countries}
                listQuery={listQuery}
            >
                <Button
                    variant="secondary"
                    size="icon"
                    className="size-8 rounded-full"
                    title="Edit short link"
                    aria-label={`Edit ${link.title || `/${link.slug}`}`}
                >
                    <Pencil aria-hidden="true" />
                </Button>
            </LinkFormDialog>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="secondary"
                        size="icon"
                        className="size-8 rounded-full"
                        title="More actions"
                        aria-label={`More actions for ${link.title || `/${link.slug}`}`}
                    >
                        <MoreHorizontal aria-hidden="true" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" className="w-52">
                    {!link.archivedAt ? (
                        <>
                            <DropdownMenuItem
                                disabled={updatingStatus}
                                onSelect={() =>
                                    runBulkAction(
                                        link.isActive
                                            ? 'deactivate'
                                            : 'activate',
                                    )
                                }
                            >
                                {link.isActive ? (
                                    <Pause aria-hidden="true" />
                                ) : (
                                    <Play aria-hidden="true" />
                                )}
                                {link.isActive ? 'Pause' : 'Activate'}
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                disabled={updatingStatus}
                                onSelect={() => runBulkAction('archive')}
                            >
                                <Archive aria-hidden="true" />
                                Archive
                            </DropdownMenuItem>
                        </>
                    ) : (
                        <DropdownMenuItem
                            disabled={updatingStatus}
                            onSelect={() => runBulkAction('restore')}
                        >
                            <Play aria-hidden="true" />
                            Restore
                        </DropdownMenuItem>
                    )}
                    <DropdownMenuItem asChild>
                        <Link
                            href={withListQuery(
                                `/dashboard/links/${link.id}/revisions`,
                                listQuery,
                            )}
                        >
                            <History aria-hidden="true" />
                            Revision history
                        </Link>
                    </DropdownMenuItem>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem
                        variant="destructive"
                        onSelect={onRequestDelete}
                    >
                        <Trash2 aria-hidden="true" />
                        Delete
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
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
    countries,
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
    countries: CountryOption[];
    listQuery: string;
    selected: boolean;
    onToggle: () => void;
}) {
    const [deleteOpen, setDeleteOpen] = useState(false);
    const shortLinkUrl = link.customDomain
        ? `https://${link.customDomain.hostname}/${link.slug}`
        : `/${link.slug}`;
    const shortLinkLabel = link.customDomain
        ? `${link.customDomain.hostname}/${link.slug}`
        : `/${link.slug}`;
    const status = link.archivedAt
        ? { label: 'Archived', dot: 'bg-muted-foreground' }
        : link.approvalStatus === 'pending'
          ? { label: 'Pending review', dot: 'bg-warning' }
          : link.isActive
            ? { label: 'Live', dot: 'bg-success' }
            : { label: 'Paused', dot: 'bg-warning' };
    const targetLabel =
        link.deliveryMode === 'link_page'
            ? `Link page: ${link.linkPage?.name ?? 'Unavailable'}`
            : (link.destinationUrl ?? 'No destination');

    return (
        <>
            <li
                className="grid gap-3 px-4 py-3.5 transition-colors hover:bg-surface-secondary/60 aria-selected:bg-primary-soft md:grid-cols-[minmax(0,2fr)_minmax(0,1.6fr)_5.5rem_8.5rem] md:items-center md:gap-4 md:py-3"
                aria-selected={selected}
            >
                <div className="flex min-w-0 items-start gap-3">
                    <div className="inline-flex size-6 shrink-0 items-center justify-center">
                        <Checkbox
                            checked={selected}
                            onCheckedChange={onToggle}
                            aria-label={`Select ${link.title || `/${link.slug}`}`}
                        />
                    </div>
                    <div className="flex min-w-0 flex-col gap-1">
                        <div className="flex min-w-0 flex-wrap items-baseline gap-x-2 gap-y-1">
                            <span className="truncate text-sm font-medium">
                                {link.title || 'Untitled route'}
                            </span>
                            <span className="inline-flex shrink-0 items-center gap-1.5 text-xs text-muted-foreground">
                                <span
                                    aria-hidden="true"
                                    className={`size-1.5 rounded-full ${status.dot}`}
                                />
                                {status.label}
                            </span>
                            {!link.archivedAt &&
                                link.approvalStatus === 'approved' &&
                                link.hasPendingRevision && (
                                    <span className="text-xs text-muted-foreground">
                                        Pending changes
                                    </span>
                                )}
                        </div>
                        <a
                            href={shortLinkUrl}
                            target="_blank"
                            rel="noreferrer"
                            title={shortLinkLabel}
                            className="inline-flex w-fit max-w-full min-w-0 items-center gap-1.5 rounded-md border border-border bg-surface-secondary px-2 py-0.5 font-mono text-[11px] text-foreground transition-colors hover:border-primary/40 hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        >
                            <span className="truncate">{shortLinkLabel}</span>
                            <ExternalLink
                                className="size-3 shrink-0 text-muted-foreground"
                                aria-hidden="true"
                            />
                            <span className="sr-only">Open short link</span>
                        </a>
                        {link.tags.length > 0 && (
                            <div className="flex flex-wrap gap-1">
                                {link.tags.map((tag) => (
                                    <Badge key={tag.id} variant="outline">
                                        {tag.name}
                                    </Badge>
                                ))}
                            </div>
                        )}
                    </div>
                </div>

                <div className="grid min-w-0 gap-1 pl-9 text-sm text-muted-foreground md:pl-0">
                    <span
                        className="flex min-w-0 items-center gap-2"
                        title={targetLabel}
                    >
                        <Globe className="size-4 shrink-0" aria-hidden="true" />
                        <span className="truncate">{targetLabel}</span>
                    </span>
                    <span className="flex min-w-0 items-center gap-2 text-xs">
                        <Folder
                            className="size-4 shrink-0"
                            aria-hidden="true"
                        />
                        <span className="truncate">
                            {link.folder?.name ?? 'No folder'}
                        </span>
                    </span>
                </div>

                <div className="flex items-center gap-2 pl-9 md:justify-end md:pl-0">
                    <MousePointerClick
                        className="size-4 shrink-0 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <span className="text-sm tabular-nums">
                        {link.clickCount}
                        {link.maxClicks !== null && (
                            <span className="text-muted-foreground">
                                {' '}
                                / {link.maxClicks}
                            </span>
                        )}
                    </span>
                    <span className="sr-only">
                        {link.clickCount === 1 ? 'click' : 'clicks'}
                    </span>
                </div>

                <div className="pl-9 md:pl-0">
                    <LinkActions
                        link={link}
                        folders={folders}
                        customDomains={customDomains}
                        linkOverlays={linkOverlays}
                        linkPages={linkPages}
                        canManageApprovals={canManageApprovals}
                        tagOptions={tagOptions}
                        countries={countries}
                        listQuery={listQuery}
                        onRequestDelete={() => setDeleteOpen(true)}
                    />
                </div>

                {(link.availableFrom ||
                    link.expiresAt ||
                    link.hasAccessCode) && (
                    <div className="col-span-full flex flex-wrap gap-x-4 gap-y-1 pl-9 text-xs text-muted-foreground md:pl-0">
                        {link.availableFrom && (
                            <span className="inline-flex items-center gap-1.5">
                                <CalendarClock
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                Starts{' '}
                                {new Date(link.availableFrom).toLocaleString()}
                            </span>
                        )}
                        {link.expiresAt && (
                            <span className="inline-flex items-center gap-1.5">
                                <CalendarClock
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                Expires{' '}
                                {new Date(link.expiresAt).toLocaleString()}
                            </span>
                        )}
                        {link.hasAccessCode && (
                            <span className="inline-flex items-center gap-1.5">
                                <KeyRound
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                Access code required
                            </span>
                        )}
                    </div>
                )}
            </li>
            <DeleteLinkDialog
                link={link}
                listQuery={listQuery}
                open={deleteOpen}
                onOpenChange={setDeleteOpen}
            />
        </>
    );
}

function listStateQuery(filters: LinkIndexProps['filters']): string {
    const query = new URLSearchParams();
    for (const [key, value] of Object.entries(filters)) {
        if (value !== '') query.set(key, value);
    }
    return query.toString();
}

function ImportLinksDialog({ listQuery }: { listQuery: string }) {
    const [open, setOpen] = useState(false);
    const form = useForm<{ file: File | null }>({ file: null });

    return (
        <Dialog
            open={open}
            onOpenChange={(nextOpen) => {
                setOpen(nextOpen);
                if (!nextOpen) {
                    form.reset();
                    form.clearErrors();
                }
            }}
        >
            <DialogTrigger asChild>
                <Button variant="outline">
                    <Upload data-icon="inline-start" />
                    Import
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Import short links</DialogTitle>
                    <DialogDescription>
                        CSV, up to 1 MB. Exported files never include access
                        codes.
                    </DialogDescription>
                </DialogHeader>
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post(
                            withListQuery('/dashboard/links/import', listQuery),
                            {
                                forceFormData: true,
                                onSuccess: () => setOpen(false),
                            },
                        );
                    }}
                    className="grid gap-3"
                >
                    <label htmlFor="short-link-csv" className="grid gap-1.5">
                        <span className="text-sm font-medium">CSV file</span>
                        <Input
                            id="short-link-csv"
                            type="file"
                            accept=".csv,text/csv"
                            aria-invalid={Boolean(form.errors.file)}
                            onChange={(event) =>
                                form.setData(
                                    'file',
                                    event.currentTarget.files?.[0] ?? null,
                                )
                            }
                        />
                        {form.errors.file && (
                            <span
                                role="alert"
                                className="text-sm text-destructive"
                            >
                                {form.errors.file}
                            </span>
                        )}
                    </label>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                Cancel
                            </Button>
                        </DialogClose>
                        <Button
                            type="submit"
                            disabled={!form.data.file || form.processing}
                        >
                            {form.processing ? (
                                <Spinner data-icon="inline-start" />
                            ) : (
                                <Upload data-icon="inline-start" />
                            )}
                            Import CSV
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function ShortLinks({
    links: linkPage,
    folders,
    customDomains,
    linkOverlays,
    linkPages,
    tagOptions,
    countries,
    canManageApprovals,
    filters,
}: LinkIndexProps) {
    const links = linkPage.data.map(toLinkListItem);
    const listQuery = listStateQuery(filters);
    const [folderFilter, setFolderFilter] = useState(filters.folder ?? '');
    const [tagFilter, setTagFilter] = useState(filters.tag ?? '');
    const [statusFilter, setStatusFilter] = useState(filters.status ?? '');
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

            <section className="mx-auto flex w-full max-w-7xl min-w-0 flex-1 flex-col gap-5 overflow-x-hidden px-5 pt-4 pb-10">
                <PageHeader
                    title="Short links"
                    description="Create compact routes, control when they resolve, and inspect the traffic they receive."
                    actions={
                        <div className="flex flex-wrap gap-2">
                            <ImportLinksDialog listQuery={listQuery} />
                            <Button variant="outline" asChild>
                                <a
                                    href={withListQuery(
                                        '/dashboard/links/export',
                                        listQuery,
                                    )}
                                >
                                    <Download data-icon="inline-start" />
                                    Export
                                </a>
                            </Button>
                            <LinkFormDialog
                                folders={folders}
                                customDomains={customDomains}
                                linkPages={linkPages}
                                linkOverlays={linkOverlays}
                                tagOptions={tagOptions}
                                countries={countries}
                                canManageApprovals={canManageApprovals}
                                listQuery={listQuery}
                            >
                                <Button>
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
                    className="flex min-w-0 flex-wrap items-center gap-2 rounded-2xl border border-border bg-card p-3 shadow-[var(--shadow-surface)]"
                >
                    <input type="hidden" name="view" value={filters.view} />
                    <Input
                        className="h-9 w-full sm:w-48"
                        name="search"
                        defaultValue={filters.search}
                        placeholder="Search routes"
                        aria-label="Search short links"
                    />
                    <input type="hidden" name="folder" value={folderFilter} />
                    <Select
                        value={folderFilter || FILTER_ALL}
                        onValueChange={(value) =>
                            setFolderFilter(value === FILTER_ALL ? '' : value)
                        }
                    >
                        <SelectTrigger
                            aria-label="Folder"
                            className="h-9 max-w-44"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={FILTER_ALL}>
                                All folders
                            </SelectItem>
                            {folders.map((folder) => (
                                <SelectItem
                                    key={folder.id}
                                    value={String(folder.id)}
                                >
                                    {folder.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <div className="flex items-center gap-1">
                        <input type="hidden" name="tag" value={tagFilter} />
                        <Select
                            value={tagFilter || FILTER_ALL}
                            onValueChange={(value) =>
                                setTagFilter(value === FILTER_ALL ? '' : value)
                            }
                        >
                            <SelectTrigger
                                aria-label="Tag"
                                className="h-9 max-w-40"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={FILTER_ALL}>
                                    All tags
                                </SelectItem>
                                {tagOptions.map((tag) => (
                                    <SelectItem
                                        key={tag.id}
                                        value={String(tag.id)}
                                    >
                                        {tag.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <CreateLinkTagDialog
                            tags={tagOptions}
                            listQuery={listQuery}
                        >
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                className="h-9"
                                aria-label="Create tag"
                                title="Create tag"
                            >
                                <Plus aria-hidden="true" />
                                Tag
                            </Button>
                        </CreateLinkTagDialog>
                    </div>
                    <input type="hidden" name="status" value={statusFilter} />
                    <Select
                        value={statusFilter || FILTER_ALL}
                        onValueChange={(value) =>
                            setStatusFilter(value === FILTER_ALL ? '' : value)
                        }
                    >
                        <SelectTrigger aria-label="Status" className="h-9">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={FILTER_ALL}>
                                All statuses
                            </SelectItem>
                            <SelectItem value="active">Active</SelectItem>
                            <SelectItem value="inactive">Inactive</SelectItem>
                            <SelectItem value="archived">Archived</SelectItem>
                            <SelectItem value="pending">
                                Pending review
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <Select name="sort" defaultValue={filters.sort}>
                        <SelectTrigger aria-label="Sort by" className="h-9">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="created_at">
                                Newest first
                            </SelectItem>
                            <SelectItem value="title">Title</SelectItem>
                            <SelectItem value="clicks_count">Clicks</SelectItem>
                        </SelectContent>
                    </Select>
                    <Select name="direction" defaultValue={filters.direction}>
                        <SelectTrigger
                            aria-label="Sort direction"
                            className="h-9 w-32"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="desc">Descending</SelectItem>
                            <SelectItem value="asc">Ascending</SelectItem>
                        </SelectContent>
                    </Select>
                    <div className="ml-auto flex items-center gap-1">
                        <Button type="submit" size="sm" className="h-9">
                            Apply
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            className="h-9"
                            asChild
                        >
                            <Link href={index()}>Reset</Link>
                        </Button>
                    </div>
                </ResourceListToolbar>

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

                <div className="flex flex-col gap-3">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div className="flex items-center gap-3">
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
                            <span className="text-sm text-muted-foreground tabular-nums">
                                {linkPage.total}{' '}
                                {linkPage.total === 1 ? 'route' : 'routes'}
                            </span>
                        </div>
                        {links.length > 0 && (
                            <Field className="min-h-11 flex-row items-center gap-2">
                                <Checkbox
                                    id="link-select-all"
                                    checked={allSelected}
                                    onCheckedChange={(checked) =>
                                        setSelectedIds(
                                            checked === true
                                                ? links.map((link) => link.id)
                                                : [],
                                        )
                                    }
                                />
                                <FieldLabel
                                    htmlFor="link-select-all"
                                    className="font-normal"
                                >
                                    Select all
                                </FieldLabel>
                            </Field>
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
                            size="sm"
                            onClick={() => bulkAction('archive')}
                        >
                            Archive
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => bulkAction('restore')}
                        >
                            Restore
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => bulkAction('activate')}
                        >
                            Activate
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => bulkAction('deactivate')}
                        >
                            Pause
                        </Button>
                        <DeleteSelectedLinksDialog
                            linkIds={selectedLinks.map((link) => link.id)}
                            listQuery={listQuery}
                            onDeleted={() => setSelectedIds([])}
                        />
                    </ResourceListBulkTray>
                </div>

                {links.length === 0 ? (
                    <Empty className="border">
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
                                countries={countries}
                            >
                                <Button>
                                    <Plus data-icon="inline-start" />
                                    Create a short link
                                </Button>
                            </LinkFormDialog>
                        </EmptyContent>
                    </Empty>
                ) : filters.view === 'table' ? (
                    <div className="lp-table-shell">
                        <div className="hidden items-center gap-4 bg-surface-secondary px-4 py-3 text-xs font-medium text-muted-foreground md:grid md:grid-cols-[minmax(0,2fr)_minmax(0,1.6fr)_5.5rem_8.5rem]">
                            <span className="pl-9">Route</span>
                            <span>Destination</span>
                            <span className="text-right">Clicks</span>
                            <span className="justify-self-end pr-1">
                                Actions
                            </span>
                        </div>
                        <ul
                            aria-label="Short links"
                            className="divide-y divide-border"
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
                                    countries={countries}
                                    listQuery={listQuery}
                                    selected={selectedIds.includes(link.id)}
                                    onToggle={() => toggleSelectedLink(link.id)}
                                />
                            ))}
                        </ul>
                    </div>
                ) : (
                    <ul
                        aria-label="Short links"
                        className="grid gap-3 [&>li]:rounded-2xl [&>li]:border [&>li]:border-border [&>li]:bg-card [&>li]:shadow-[var(--shadow-surface)]"
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
                                countries={countries}
                                listQuery={listQuery}
                                selected={selectedIds.includes(link.id)}
                                onToggle={() => toggleSelectedLink(link.id)}
                            />
                        ))}
                    </ul>
                )}

                <ResourceListPagination
                    links={linkPage.links}
                    total={linkPage.total}
                    ariaLabel="Short link pages"
                    singular="link"
                    plural="links"
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
