import { Head, router } from '@inertiajs/react';
import { Plus, Sparkles, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
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
import { Spinner } from '@/components/ui/spinner';
import { destroy } from '@/routes/overlays';
import type { LinkOverlay, OverlaysPageProps } from '@/types/overlays';
import {
    CreateOverlayDialog,
    EditOverlayDialog,
} from '@/components/overlays/overlay-form-dialogs';

function DeleteOverlayDialog({ overlay }: { overlay: LinkOverlay }) {
    const [open, setOpen] = useState(false);
    const [processing, setProcessing] = useState(false);

    function remove() {
        router.delete(destroy(overlay).url, {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onSuccess: () => setOpen(false),
        });
    }

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button type="button" size="sm" variant="outline">
                    <Trash2 data-icon="inline-start" />
                    Remove
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Remove {overlay.name}?</DialogTitle>
                    <DialogDescription>
                        Links using this overlay will resume direct delivery to
                        their destinations.
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
                        onClick={remove}
                        disabled={processing}
                    >
                        {processing && <Spinner data-icon="inline-start" />}
                        Remove overlay
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

export default function OverlaysIndex({
    overlays,
    canManageIntegrations,
}: OverlaysPageProps) {
    return (
        <>
            <Head title="CTA overlays" />

            <div className="mx-auto flex w-full max-w-7xl min-w-0 flex-col gap-5 px-5 pt-4 pb-10">
                <PageHeader
                    title="CTA overlays"
                    description="Show a LinkPilot-controlled message before a short link delivers its destination."
                    actions={
                        canManageIntegrations ? (
                            <CreateOverlayDialog>
                                <Button>
                                    <Plus data-icon="inline-start" />
                                    Create overlay
                                </Button>
                            </CreateOverlayDialog>
                        ) : undefined
                    }
                />

                {overlays.length === 0 ? (
                    <Empty className="border">
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <Sparkles />
                            </EmptyMedia>
                            <EmptyTitle>No overlays yet</EmptyTitle>
                            <EmptyDescription>
                                {canManageIntegrations
                                    ? 'Create an interstitial, then assign it to a short link from the links page.'
                                    : 'There are no overlays in this workspace yet. An owner or admin can add one.'}
                            </EmptyDescription>
                        </EmptyHeader>
                        {canManageIntegrations && (
                            <EmptyContent>
                                <CreateOverlayDialog>
                                    <Button>
                                        <Plus data-icon="inline-start" />
                                        Create your first overlay
                                    </Button>
                                </CreateOverlayDialog>
                            </EmptyContent>
                        )}
                    </Empty>
                ) : (
                    <div className="grid gap-4 lg:grid-cols-2">
                        {overlays.map((overlay) => (
                            <Card
                                id={`overlay-${overlay.id}`}
                                key={overlay.id}
                                className="scroll-mt-20"
                            >
                                <CardHeader>
                                    <div className="flex flex-wrap items-start justify-between gap-3">
                                        <div className="min-w-0 space-y-1">
                                            <CardTitle>
                                                {overlay.name}
                                            </CardTitle>
                                            <CardDescription>
                                                {overlay.heading}
                                            </CardDescription>
                                        </div>
                                        <Badge
                                            variant={
                                                overlay.isActive
                                                    ? 'default'
                                                    : 'secondary'
                                            }
                                        >
                                            {overlay.isActive
                                                ? 'Active'
                                                : 'Inactive'}
                                        </Badge>
                                    </div>
                                </CardHeader>
                                <CardContent className="space-y-2 text-sm text-muted-foreground">
                                    {overlay.body && <p>{overlay.body}</p>}
                                    <p>
                                        Button: {overlay.ctaLabel} ·{' '}
                                        {overlay.linksCount} assigned{' '}
                                        {overlay.linksCount === 1
                                            ? 'link'
                                            : 'links'}
                                    </p>
                                </CardContent>
                                {canManageIntegrations && (
                                    <CardFooter className="flex flex-wrap gap-2">
                                        <EditOverlayDialog overlay={overlay} />
                                        <DeleteOverlayDialog
                                            overlay={overlay}
                                        />
                                    </CardFooter>
                                )}
                            </Card>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}
