import { Head, Link, router } from '@inertiajs/react';
import {
    FolderOpen,
    MousePointerClick,
    Pencil,
    Plus,
    Trash2,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { PageHeader } from '@/components/page-header';
import { ResourceListBulkTray } from '@/components/resource-list/bulk-tray';
import { ResourceListFilterChips } from '@/components/resource-list/filter-chips';
import { FolderFormDialog } from '@/components/folders/folder-form-dialog';
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
import { Separator } from '@/components/ui/separator';
import { Spinner } from '@/components/ui/spinner';
import { destroy, index } from '@/routes/folders';
import type { FolderIndexProps, FolderPayload } from '@/types/folders';

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

function DeleteFolderDialog({
    folder,
    listQuery,
}: {
    folder: FolderPayload;
    listQuery: string;
}) {
    const [open, setOpen] = useState(false);
    const [processing, setProcessing] = useState(false);

    function deleteFolder() {
        router.delete(withListQuery(destroy(folder.id).url, listQuery), {
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
                    <DialogTitle>Delete this folder?</DialogTitle>
                    <DialogDescription>
                        Its links will stay available and move back to no
                        folder.
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
                        onClick={deleteFolder}
                        disabled={processing}
                    >
                        {processing && <Spinner data-icon="inline-start" />}
                        Delete folder
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function DeleteSelectedFoldersDialog({
    folderIds,
    listQuery,
    onDeleted,
}: {
    folderIds: number[];
    listQuery: string;
    onDeleted: () => void;
}) {
    const [open, setOpen] = useState(false);
    const [processing, setProcessing] = useState(false);

    function deleteSelectedFolders() {
        router.delete(withListQuery('/dashboard/folders', listQuery), {
            data: { ids: folderIds },
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onSuccess: () => {
                setOpen(false);
                onDeleted();
            },
            onFinish: () => setProcessing(false),
        });
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
                    <DialogTitle>Delete selected folders?</DialogTitle>
                    <DialogDescription>
                        {folderIds.length} folders will be removed. Their links
                        will stay available and move back to no folder.
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
                        onClick={deleteSelectedFolders}
                        disabled={processing}
                    >
                        {processing && <Spinner data-icon="inline-start" />}
                        Delete folders
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function FolderCard({
    folder,
    listQuery,
    selected,
    onToggle,
}: {
    folder: FolderPayload;
    listQuery: string;
    selected: boolean;
    onToggle: () => void;
}) {
    const linksLabel = `${folder.links_count} ${
        folder.links_count === 1 ? 'link' : 'links'
    }`;
    const clicksLabel = `${folder.clicks_count} ${
        folder.clicks_count === 1 ? 'click' : 'clicks'
    }`;
    const eventsLabel = `${folder.analytics_events_count} ${
        folder.analytics_events_count === 1 ? 'event' : 'events'
    }`;

    return (
        <Card>
            <CardHeader className="gap-3">
                <div className="flex items-start justify-between gap-4">
                    <div className="flex min-w-0 items-start gap-3">
                        <label className="inline-flex min-h-11 min-w-11 shrink-0 items-center justify-center">
                            <input
                                type="checkbox"
                                checked={selected}
                                onChange={onToggle}
                                aria-label={`Select folder ${folder.name}`}
                                className="size-4 accent-primary focus-visible:ring-2 focus-visible:ring-ring"
                            />
                        </label>
                        <div className="min-w-0">
                            <CardTitle className="truncate">
                                {folder.name}
                            </CardTitle>
                            <CardDescription>
                                A private collection for related routes.
                            </CardDescription>
                        </div>
                    </div>
                    <FolderOpen className="size-5 shrink-0 text-primary" />
                </div>
            </CardHeader>
            <CardContent>
                <Separator />
                <dl className="mt-4 grid grid-cols-3 gap-4">
                    <div>
                        <dt className="text-sm text-muted-foreground">Links</dt>
                        <dd className="mt-1 font-medium tabular-nums">
                            {linksLabel}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-sm text-muted-foreground">
                            Clicks
                        </dt>
                        <dd className="mt-1 flex items-center gap-1 font-medium tabular-nums">
                            <MousePointerClick className="size-4 text-muted-foreground" />
                            {clicksLabel}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-sm text-muted-foreground">
                            Events
                        </dt>
                        <dd className="mt-1 font-medium tabular-nums">
                            {eventsLabel}
                        </dd>
                    </div>
                </dl>
            </CardContent>
            <CardFooter className="flex flex-wrap gap-2 [&_button]:min-h-11 [&_button]:min-w-11">
                <FolderFormDialog folder={folder} listQuery={listQuery}>
                    <Button variant="outline" size="sm">
                        <Pencil data-icon="inline-start" />
                        Rename
                    </Button>
                </FolderFormDialog>
                <DeleteFolderDialog folder={folder} listQuery={listQuery} />
            </CardFooter>
        </Card>
    );
}

function listStateQuery(filters: FolderIndexProps['filters']): string {
    const query = new URLSearchParams();
    for (const [key, value] of Object.entries(filters)) {
        if (value !== '') query.set(key, value);
    }
    return query.toString();
}

export default function FoldersIndex({
    folders: folderPage,
    filters,
}: FolderIndexProps) {
    const folders = folderPage.data;
    const listQuery = listStateQuery(filters);
    const [selectedIds, setSelectedIds] = useState<number[]>([]);
    useEffect(() => {
        const visibleIds = new Set(folderPage.data.map((folder) => folder.id));
        setSelectedIds((current) => current.filter((id) => visibleIds.has(id)));
    }, [folderPage.data]);
    const selectedFolders = folders.filter((folder) =>
        selectedIds.includes(folder.id),
    );
    const allSelected =
        folders.length > 0 && selectedFolders.length === folders.length;

    function toggleSelectedFolder(id: number) {
        setSelectedIds((current) =>
            current.includes(id)
                ? current.filter((selectedId) => selectedId !== id)
                : [...current, id],
        );
    }

    return (
        <>
            <Head title="Folders" />
            <div className="flex min-w-0 flex-col gap-6 overflow-x-hidden p-4 sm:p-6">
                <PageHeader
                    title="Folders"
                    icon={FolderOpen}
                    description="Keep campaigns and destinations grouped together."
                    actions={
                        <FolderFormDialog listQuery={listQuery}>
                            <Button className="min-h-11 min-w-11">
                                <Plus data-icon="inline-start" />
                                New folder
                            </Button>
                        </FolderFormDialog>
                    }
                />
                <ResourceListToolbar
                    key={listQuery}
                    action={index().url}
                    className="grid min-w-0 gap-3 rounded-lg border bg-card p-4 sm:grid-cols-2 lg:grid-cols-4 [&_button]:min-h-11 [&_button]:min-w-11"
                >
                    <input type="hidden" name="view" value={filters.view} />
                    <Field className="sm:col-span-2">
                        <Input
                            className="min-h-11 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            name="search"
                            defaultValue={filters.search}
                            placeholder="Search folder names"
                            aria-label="Search folders"
                        />
                    </Field>
                    <ResourceListSortMenu
                        value={filters.sort}
                        options={[
                            { value: 'created_at', label: 'Created' },
                            { value: 'name', label: 'Name' },
                            { value: 'links_count', label: 'Link count' },
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
                    <div className="flex items-end gap-2 sm:col-span-2 lg:col-span-4">
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
                <ResourceListFilterChips
                    filters={
                        filters.search
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
                            : []
                    }
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
                    {folders.length > 0 && (
                        <label className="inline-flex min-h-11 items-center gap-2 text-sm">
                            <input
                                type="checkbox"
                                checked={allSelected}
                                onChange={() =>
                                    setSelectedIds(
                                        allSelected
                                            ? []
                                            : folders.map(
                                                  (folder) => folder.id,
                                              ),
                                    )
                                }
                                aria-label="Select all folders on this page"
                                className="size-4 accent-primary focus-visible:ring-2 focus-visible:ring-ring"
                            />
                            Select all on this page
                        </label>
                    )}
                </div>
                <ResourceListBulkTray
                    count={selectedFolders.length}
                    singular="folder"
                    plural="folders"
                    onClear={() => setSelectedIds([])}
                >
                    <DeleteSelectedFoldersDialog
                        folderIds={selectedFolders.map((folder) => folder.id)}
                        listQuery={listQuery}
                        onDeleted={() => setSelectedIds([])}
                    />
                </ResourceListBulkTray>

                {folders.length === 0 ? (
                    <Empty className="border">
                        <EmptyHeader className="w-full">
                            <EmptyMedia variant="icon">
                                <FolderOpen />
                            </EmptyMedia>
                            <EmptyTitle>
                                {filters.search
                                    ? 'No matching folders'
                                    : 'No folders yet'}
                            </EmptyTitle>
                            <EmptyDescription className="w-full min-w-0 break-words">
                                {filters.search
                                    ? `No folders match “${filters.search}”.`
                                    : 'Create a folder when links start to multiply.'}
                            </EmptyDescription>
                        </EmptyHeader>
                        <EmptyContent>
                            <FolderFormDialog listQuery={listQuery}>
                                <Button className="min-h-11 min-w-11">
                                    <Plus data-icon="inline-start" />
                                    Create folder
                                </Button>
                            </FolderFormDialog>
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
                                            <th className="w-[36%] p-3">
                                                Folder
                                            </th>
                                            <th className="w-[12%] p-3">
                                                Links
                                            </th>
                                            <th className="w-[12%] p-3">
                                                Clicks
                                            </th>
                                            <th className="w-[12%] p-3">
                                                Events
                                            </th>
                                            <th className="w-[24%] p-3">
                                                Actions
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {folders.map((folder) => (
                                            <tr
                                                key={folder.id}
                                                className="border-t"
                                                aria-selected={selectedIds.includes(
                                                    folder.id,
                                                )}
                                            >
                                                <td className="p-3">
                                                    <label className="inline-flex min-h-11 min-w-11 items-center justify-center">
                                                        <input
                                                            type="checkbox"
                                                            checked={selectedIds.includes(
                                                                folder.id,
                                                            )}
                                                            onChange={() =>
                                                                toggleSelectedFolder(
                                                                    folder.id,
                                                                )
                                                            }
                                                            aria-label={`Select folder ${folder.name}`}
                                                            className="size-4 accent-primary focus-visible:ring-2 focus-visible:ring-ring"
                                                        />
                                                    </label>
                                                </td>
                                                <td className="truncate p-3 font-medium">
                                                    {folder.name}
                                                </td>
                                                <td className="p-3 tabular-nums">
                                                    {folder.links_count}
                                                </td>
                                                <td className="p-3 tabular-nums">
                                                    {folder.clicks_count}
                                                </td>
                                                <td className="p-3 tabular-nums">
                                                    {
                                                        folder.analytics_events_count
                                                    }
                                                </td>
                                                <td className="p-3">
                                                    <div className="flex flex-wrap gap-2 [&_button]:min-h-11 [&_button]:min-w-11">
                                                        <FolderFormDialog
                                                            folder={folder}
                                                            listQuery={
                                                                listQuery
                                                            }
                                                        >
                                                            <Button
                                                                variant="outline"
                                                                size="sm"
                                                            >
                                                                <Pencil data-icon="inline-start" />
                                                                Rename
                                                            </Button>
                                                        </FolderFormDialog>
                                                        <DeleteFolderDialog
                                                            folder={folder}
                                                            listQuery={
                                                                listQuery
                                                            }
                                                        />
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                        <div
                            className={`min-w-0 gap-4 md:grid-cols-2 xl:grid-cols-3 ${filters.view === 'cards' ? 'grid' : 'grid min-[1100px]:hidden'}`}
                        >
                            {folders.map((folder) => (
                                <FolderCard
                                    key={folder.id}
                                    folder={folder}
                                    listQuery={listQuery}
                                    selected={selectedIds.includes(folder.id)}
                                    onToggle={() =>
                                        toggleSelectedFolder(folder.id)
                                    }
                                />
                            ))}
                        </div>
                    </>
                )}
                <ResourceListPagination
                    links={folderPage.links}
                    total={folderPage.total}
                    ariaLabel="Folder pages"
                    summaryLabel="folders"
                />
            </div>
        </>
    );
}
