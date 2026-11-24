import { Head, router } from '@inertiajs/react';
import { Plus, ShieldCheck, Trash2 } from 'lucide-react';
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
import { destroy } from '@/routes/tracking-pixels';
import type {
    TrackingPixel,
    TrackingPixelProvider,
    TrackingPixelsPageProps,
} from '@/types/link-pages';
import {
    CreatePixelDialog,
    EditPixelDialog,
    PixelRules,
} from '@/components/tracking-pixels/pixel-editor';

function providerLabel(provider: TrackingPixelProvider): string {
    return provider === 'meta_pixel' ? 'Meta Pixel' : 'Google Analytics';
}

function DeletePixelDialog({ pixel }: { pixel: TrackingPixel }) {
    const [open, setOpen] = useState(false);
    const [processing, setProcessing] = useState(false);

    function remove() {
        router.delete(destroy(pixel).url, {
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
                    <DialogTitle>Remove {pixel.name}?</DialogTitle>
                    <DialogDescription>
                        Public pages using this pixel stop loading it
                        immediately.
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
                        Remove pixel
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

export default function TrackingPixelsIndex({
    pixels,
    canManageIntegrations,
    availableTargets,
}: TrackingPixelsPageProps) {
    return (
        <>
            <Head title="Tracking pixels" />

            <div className="mx-auto flex w-full max-w-7xl min-w-0 flex-col gap-5 px-5 pt-4 pb-10">
                <PageHeader
                    title="Tracking pixels"
                    description="Add fixed Meta Pixel or Google Analytics integrations for LinkPilot-controlled public pages."
                    actions={
                        canManageIntegrations ? (
                            <CreatePixelDialog>
                                <Button>
                                    <Plus data-icon="inline-start" />
                                    Add pixel
                                </Button>
                            </CreatePixelDialog>
                        ) : undefined
                    }
                />

                {pixels.length === 0 ? (
                    <Empty className="border">
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <ShieldCheck />
                            </EmptyMedia>
                            <EmptyTitle>No tracking pixels yet</EmptyTitle>
                            <EmptyDescription>
                                {canManageIntegrations
                                    ? 'Create a fixed provider integration, then assign it a trigger rule for a public surface.'
                                    : 'There are no tracking pixels in this workspace yet. An owner or admin can add one.'}
                            </EmptyDescription>
                        </EmptyHeader>
                        {canManageIntegrations && (
                            <EmptyContent>
                                <CreatePixelDialog>
                                    <Button>
                                        <Plus data-icon="inline-start" />
                                        Add your first pixel
                                    </Button>
                                </CreatePixelDialog>
                            </EmptyContent>
                        )}
                    </Empty>
                ) : (
                    <div className="grid gap-4 lg:grid-cols-2">
                        {pixels.map((pixel) => (
                            <Card
                                id={`tracking-pixel-${pixel.id}`}
                                key={pixel.id}
                                className="scroll-mt-20"
                            >
                                <CardHeader>
                                    <div className="flex flex-wrap items-start justify-between gap-3">
                                        <div className="space-y-1">
                                            <CardTitle>{pixel.name}</CardTitle>
                                            <CardDescription>
                                                {providerLabel(pixel.provider)}{' '}
                                                ·{' '}
                                                <span className="font-mono">
                                                    {pixel.providerId}
                                                </span>
                                            </CardDescription>
                                        </div>
                                        <Badge
                                            variant={
                                                pixel.isActive
                                                    ? 'default'
                                                    : 'secondary'
                                            }
                                        >
                                            {pixel.isActive
                                                ? 'Active'
                                                : 'Inactive'}
                                        </Badge>
                                    </div>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <p className="text-sm text-muted-foreground">
                                        {pixel.rules.length} trigger{' '}
                                        {pixel.rules.length === 1
                                            ? 'rule'
                                            : 'rules'}
                                    </p>
                                    <PixelRules
                                        pixel={pixel}
                                        targets={availableTargets}
                                        editable={canManageIntegrations}
                                    />
                                </CardContent>
                                {canManageIntegrations && (
                                    <CardFooter className="flex flex-wrap gap-2">
                                        <EditPixelDialog pixel={pixel} />
                                        <DeletePixelDialog pixel={pixel} />
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
