import { Head, router } from '@inertiajs/react';
import {
    FolderOpen,
    MousePointerClick,
    Pencil,
    Plus,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import { FolderFormDialog } from '@/components/folders/folder-form-dialog';
import { Button } from '@/components/ui/button';
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
import { destroy } from '@/routes/folders';
import type { FolderIndexProps, FolderPayload } from '@/types/folders';

function DeleteFolderDialog({ folder }: { folder: FolderPayload }) {
    const [open, setOpen] = useState(false);
    const [processing, setProcessing] = useState(false);

    function deleteFolder() {
        router.delete(destroy(folder.id).url, {
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
                        Its links will stay available and move back to no folder.
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

function FolderCard({ folder }: { folder: FolderPayload }) {
    const linksLabel = `${folder.links_count} ${
        folder.links_count === 1 ? 'link' : 'links'
    }`;
    const clicksLabel = `${folder.clicks_count} ${
        folder.clicks_count === 1 ? 'click' : 'clicks'
    }`;

    return (
        <Card>
            <CardHeader className="gap-3">
                <div className="flex items-start justify-between gap-4">
                    <div className="min-w-0">
                        <CardTitle className="truncate">{folder.name}</CardTitle>
                        <CardDescription>
                            A private collection for related routes.
                        </CardDescription>
                    </div>
                    <FolderOpen className="size-5 shrink-0 text-primary" />
                </div>
            </CardHeader>
            <CardContent>
                <Separator />
                <dl className="mt-4 grid grid-cols-2 gap-4">
                    <div>
                        <dt className="text-sm text-muted-foreground">Links</dt>
                        <dd className="mt-1 font-medium tabular-nums">
                            {linksLabel}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-sm text-muted-foreground">Clicks</dt>
                        <dd className="mt-1 flex items-center gap-1 font-medium tabular-nums">
                            <MousePointerClick className="size-4 text-muted-foreground" />
                            {clicksLabel}
                        </dd>
                    </div>
                </dl>
            </CardContent>
            <CardFooter className="flex flex-wrap gap-2">
                <FolderFormDialog folder={folder}>
                    <Button variant="outline" size="sm">
                        <Pencil data-icon="inline-start" />
                        Rename
                    </Button>
                </FolderFormDialog>
                <DeleteFolderDialog folder={folder} />
            </CardFooter>
        </Card>
    );
}

export default function FoldersIndex({ folders }: FolderIndexProps) {
    return (
        <>
            <Head title="Folders" />
            <div className="flex flex-col gap-6 p-4 sm:p-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div className="space-y-1">
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Folders
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Keep campaigns and destinations grouped together.
                        </p>
                    </div>
                    <FolderFormDialog>
                        <Button>
                            <Plus data-icon="inline-start" />
                            New folder
                        </Button>
                    </FolderFormDialog>
                </div>

                {folders.length === 0 ? (
                    <Empty className="border">
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <FolderOpen />
                            </EmptyMedia>
                            <EmptyTitle>No folders yet</EmptyTitle>
                            <EmptyDescription>
                                Create a folder when links start to multiply.
                            </EmptyDescription>
                        </EmptyHeader>
                        <EmptyContent>
                            <FolderFormDialog>
                                <Button>
                                    <Plus data-icon="inline-start" />
                                    Create folder
                                </Button>
                            </FolderFormDialog>
                        </EmptyContent>
                    </Empty>
                ) : (
                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        {folders.map((folder) => (
                            <FolderCard key={folder.id} folder={folder} />
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}
