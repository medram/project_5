import { Head, Link, router } from '@inertiajs/react';
import {
    Activity,
    FolderOpen,
    Link2,
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
                <Button
                    variant="destructive"
                    size="icon"
                    className="size-8 rounded-full"
                    title="Delete folder"
                    aria-label={`Delete ${folder.name}`}
                >
                    <Trash2 aria-hidden="true" />
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
                <Button type="button" variant="outline" size="sm">
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
    return (
        <Card>
            <CardHeader className="gap-3">
                <div className="flex items-start justify-between gap-4">
                    <div className="flex min-w-0 items-start gap-3">
                        <div className="inline-flex size-6 shrink-0 items-center justify-center">
                            <Checkbox
                                checked={selected}
                                onCheckedChange={onToggle}
                                aria-label={`Select folder ${folder.name}`}
                            />
                        </div>
                        <div className="min-w-0">
                            <CardTitle className="truncate">
                                {folder.name}
                            </CardTitle>
                            <CardDescription>
                                A private collection for related routes.
                            </CardDescription>
                        </div>
                    </div>
                </div>
            </CardHeader>
            <CardContent>
                <Separator />
                <dl className="mt-4 grid grid-cols-3 gap-4">
                    {[
                        {
                            label: 'Links',
                            icon: Link2,
                            value: folder.links_count,
                        },
                        {
                            label: 'Clicks',
                            icon: MousePointerClick,
                            value: folder.clicks_count,
                        },
                        {
                            label: 'Events',
                            icon: Activity,
                            value: folder.analytics_events_count,
                        },
                    ].map(({ label, icon: Icon, value }) => (
                        <div key={label}>
                            <dt className="flex items-center gap-1.5 text-xs text-muted-foreground">
                                <Icon
                                    className="size-4 shrink-0"
                                    aria-hidden="true"
                                />
                                {label}
                            </dt>
                            <dd className="mt-1 text-sm tabular-nums">
                                {value}
                            </dd>
                        </div>
                    ))}
                </dl>
            </CardContent>
            <CardFooter className="flex flex-wrap gap-2">
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
            <div className="mx-auto flex w-full max-w-7xl min-w-0 flex-col gap-5 overflow-x-hidden px-5 pt-4 pb-10">
                <PageHeader
                    title="Folders"
                    description="Keep campaigns and destinations grouped together."
                    actions={
                        <FolderFormDialog listQuery={listQuery}>
                            <Button>
                                <Plus data-icon="inline-start" />
                                New folder
                            </Button>
                        </FolderFormDialog>
                    }
                />
                <ResourceListToolbar
                    key={listQuery}
                    action={index().url}
                    className="flex min-w-0 flex-wrap items-center gap-2 rounded-xl border bg-card p-3"
                >
                    <input type="hidden" name="view" value={filters.view} />
                    <Input
                        className="h-9 w-full sm:w-56"
                        name="search"
                        defaultValue={filters.search}
                        placeholder="Search folders"
                        aria-label="Search folders"
                    />
                    <Select name="sort" defaultValue={filters.sort}>
                        <SelectTrigger aria-label="Sort by" className="h-9">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="created_at">
                                Newest first
                            </SelectItem>
                            <SelectItem value="name">Name</SelectItem>
                            <SelectItem value="links_count">
                                Link count
                            </SelectItem>
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
                        <Field className="min-h-11 flex-row items-center gap-2">
                            <Checkbox
                                id="folder-select-all"
                                checked={allSelected}
                                onCheckedChange={(checked) =>
                                    setSelectedIds(
                                        checked === true
                                            ? folders.map((folder) => folder.id)
                                            : [],
                                    )
                                }
                            />
                            <FieldLabel
                                htmlFor="folder-select-all"
                                className="font-normal"
                            >
                                Select all on this page
                            </FieldLabel>
                        </Field>
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
                                <Button>
                                    <Plus data-icon="inline-start" />
                                    Create folder
                                </Button>
                            </FolderFormDialog>
                        </EmptyContent>
                    </Empty>
                ) : (
                    <>
                        {filters.view === 'table' && (
                            <div className="lp-table-shell hidden min-w-0 min-[1100px]:block">
                                <table
                                    data-table-surface="populated"
                                    className="w-full table-fixed text-left text-sm"
                                >
                                    <thead className="bg-surface-secondary text-muted-foreground">
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
                                                data-selected={
                                                    selectedIds.includes(
                                                        folder.id,
                                                    ) || undefined
                                                }
                                            >
                                                <td className="p-3">
                                                    <div className="inline-flex min-h-11 min-w-11 items-center justify-center">
                                                        <Checkbox
                                                            checked={selectedIds.includes(
                                                                folder.id,
                                                            )}
                                                            onCheckedChange={() =>
                                                                toggleSelectedFolder(
                                                                    folder.id,
                                                                )
                                                            }
                                                            aria-label={`Select folder ${folder.name}`}
                                                        />
                                                    </div>
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
                                                    <div className="flex flex-wrap justify-end gap-2">
                                                        <FolderFormDialog
                                                            folder={folder}
                                                            listQuery={
                                                                listQuery
                                                            }
                                                        >
                                                            <Button
                                                                variant="secondary"
                                                                size="icon"
                                                                className="size-8 rounded-full"
                                                                title="Rename folder"
                                                                aria-label={`Rename ${folder.name}`}
                                                            >
                                                                <Pencil aria-hidden="true" />
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
                    singular="folder"
                    plural="folders"
                />
            </div>
        </>
    );
}
