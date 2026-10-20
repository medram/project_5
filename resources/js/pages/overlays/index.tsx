import { Head, router, useForm } from '@inertiajs/react';
import { Layers, Pencil, Plus, Sparkles, Trash2 } from 'lucide-react';
import { type FormEvent, type ReactNode, useState } from 'react';
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
import { Checkbox } from '@/components/ui/checkbox';
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
import {
    Field,
    FieldDescription,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { destroy, index, store, update } from '@/routes/overlays';
import type {
    LinkOverlay,
    OverlayPixel,
    OverlaysPageProps,
} from '@/types/overlays';

type OverlayFormData = {
    name: string;
    heading: string;
    body: string;
    cta_label: string;
    is_active: boolean;
    tracking_pixel_ids: number[];
};

type OverlayFormFieldsProps = {
    data: OverlayFormData;
    errors: Partial<Record<keyof OverlayFormData, string>>;
    processing: boolean;
    availablePixels: OverlayPixel[];
    setData: (key: keyof OverlayFormData, value: string | boolean) => void;
};

function providerLabel(pixel: OverlayPixel): string {
    return pixel.provider === 'meta_pixel' ? 'Meta Pixel' : 'Google Analytics';
}

function OverlayFormFields({
    data,
    errors,
    processing,
    availablePixels,
    setData,
}: OverlayFormFieldsProps) {
    function togglePixel(pixel: OverlayPixel) {
        if (!pixel.isActive) {
            return;
        }

        setData(
            'tracking_pixel_ids',
            data.tracking_pixel_ids.includes(pixel.id)
                ? data.tracking_pixel_ids.filter((id) => id !== pixel.id)
                : [...data.tracking_pixel_ids, pixel.id],
        );
    }

    return (
        <FieldGroup>
            <Field data-invalid={Boolean(errors.name)}>
                <FieldLabel htmlFor="overlay-name">Internal name</FieldLabel>
                <Input
                    id="overlay-name"
                    value={data.name}
                    onChange={(event) => setData('name', event.target.value)}
                    placeholder="Launch overlay"
                    maxLength={100}
                    disabled={processing}
                    aria-invalid={Boolean(errors.name)}
                />
                {errors.name && <FieldError>{errors.name}</FieldError>}
            </Field>

            <Field data-invalid={Boolean(errors.heading)}>
                <FieldLabel htmlFor="overlay-heading">Heading</FieldLabel>
                <Input
                    id="overlay-heading"
                    value={data.heading}
                    onChange={(event) => setData('heading', event.target.value)}
                    placeholder="Before you go"
                    maxLength={160}
                    disabled={processing}
                    aria-invalid={Boolean(errors.heading)}
                />
                {errors.heading && <FieldError>{errors.heading}</FieldError>}
            </Field>

            <Field data-invalid={Boolean(errors.body)}>
                <FieldLabel htmlFor="overlay-body">Body</FieldLabel>
                <Textarea
                    id="overlay-body"
                    value={data.body}
                    onChange={(event) => setData('body', event.target.value)}
                    placeholder="A short message shown before the destination."
                    maxLength={500}
                    disabled={processing}
                    aria-invalid={Boolean(errors.body)}
                />
                {errors.body && <FieldError>{errors.body}</FieldError>}
            </Field>

            <Field data-invalid={Boolean(errors.cta_label)}>
                <FieldLabel htmlFor="overlay-cta">Button label</FieldLabel>
                <Input
                    id="overlay-cta"
                    value={data.cta_label}
                    onChange={(event) =>
                        setData('cta_label', event.target.value)
                    }
                    placeholder="Continue"
                    maxLength={100}
                    disabled={processing}
                    aria-invalid={Boolean(errors.cta_label)}
                />
                {errors.cta_label && (
                    <FieldError>{errors.cta_label}</FieldError>
                )}
            </Field>

            <Field orientation="horizontal">
                <Checkbox
                    id="overlay-active"
                    checked={data.is_active}
                    onCheckedChange={(checked) =>
                        setData('is_active', checked === true)
                    }
                    disabled={processing}
                />
                <div className="grid gap-1.5 leading-none">
                    <FieldLabel htmlFor="overlay-active">Active</FieldLabel>
                    <FieldDescription>
                        Only an active overlay can be assigned to a short link.
                    </FieldDescription>
                </div>
            </Field>

            {availablePixels.length > 0 && (
                <Field>
                    <FieldLabel>Tracking pixels</FieldLabel>
                    <FieldDescription>
                        Attach up to five active pixels. They load only after a
                        visitor grants consent.
                    </FieldDescription>
                    <div className="mt-2 space-y-2">
                        {availablePixels.map((pixel) => {
                            const attached =
                                data.tracking_pixel_ids.includes(pixel.id);

                            return (
                                <label
                                    key={pixel.id}
                                    className="flex cursor-pointer items-start gap-3 rounded-lg border p-3 has-[:disabled]:cursor-not-allowed has-[:disabled]:opacity-60"
                                >
                                    <Checkbox
                                        checked={attached}
                                        onCheckedChange={() => togglePixel(pixel)}
                                        disabled={
                                            !pixel.isActive ||
                                            (data.tracking_pixel_ids.length >= 5 &&
                                                !attached) ||
                                            processing
                                        }
                                    />
                                    <span className="grid gap-1">
                                        <span className="flex flex-wrap items-center gap-2 text-sm font-medium">
                                            {pixel.name}
                                            {!pixel.isActive && (
                                                <Badge variant="secondary">
                                                    Inactive
                                                </Badge>
                                            )}
                                        </span>
                                        <span className="text-xs text-muted-foreground">
                                            {providerLabel(pixel)} ·{' '}
                                            {pixel.providerId}
                                        </span>
                                    </span>
                                </label>
                            );
                        })}
                    </div>
                    {errors.tracking_pixel_ids && (
                        <FieldError className="mt-2">
                            {errors.tracking_pixel_ids}
                        </FieldError>
                    )}
                </Field>
            )}
        </FieldGroup>
    );
}

function CreateOverlayDialog({
    children,
    availablePixels,
}: {
    children: ReactNode;
    availablePixels: OverlayPixel[];
}) {
    const [open, setOpen] = useState(false);
    const form = useForm<OverlayFormData>({
        name: '',
        heading: '',
        body: '',
        cta_label: 'Continue',
        is_active: true,
        tracking_pixel_ids: [],
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post(store().url, {
            onSuccess: () => {
                form.reset();
                setOpen(false);
            },
        });
    }

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{children}</DialogTrigger>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Create overlay</DialogTitle>
                    <DialogDescription>
                        A LinkPilot-controlled interstitial shown before a short
                        link delivers its destination.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit}>
                    <OverlayFormFields
                        data={form.data}
                        errors={form.errors}
                        processing={form.processing}
                        availablePixels={availablePixels}
                        setData={form.setData}
                    />
                    <DialogFooter className="mt-6">
                        <DialogClose asChild>
                            <Button
                                type="button"
                                variant="outline"
                                disabled={form.processing}
                            >
                                Cancel
                            </Button>
                        </DialogClose>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && (
                                <Spinner data-icon="inline-start" />
                            )}
                            Create overlay
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function EditOverlayDialog({
    overlay,
    availablePixels,
}: {
    overlay: LinkOverlay;
    availablePixels: OverlayPixel[];
}) {
    const [open, setOpen] = useState(false);
    const form = useForm<OverlayFormData>({
        name: overlay.name,
        heading: overlay.heading,
        body: overlay.body ?? '',
        cta_label: overlay.ctaLabel,
        is_active: overlay.isActive,
        tracking_pixel_ids: overlay.trackingPixelIds.filter((pixelId) =>
            availablePixels.some(
                (pixel) => pixel.id === pixelId && pixel.isActive,
            ),
        ),
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.patch(update(overlay).url, {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    }

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button type="button" size="sm" variant="outline">
                    <Pencil data-icon="inline-start" />
                    Edit
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Edit {overlay.name}</DialogTitle>
                    <DialogDescription>
                        Deactivating an overlay returns its assigned links to
                        direct delivery.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit}>
                    <OverlayFormFields
                        data={form.data}
                        errors={form.errors}
                        processing={form.processing}
                        availablePixels={availablePixels}
                        setData={form.setData}
                    />
                    <DialogFooter className="mt-6">
                        <DialogClose asChild>
                            <Button
                                type="button"
                                variant="outline"
                                disabled={form.processing}
                            >
                                Cancel
                            </Button>
                        </DialogClose>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && (
                                <Spinner data-icon="inline-start" />
                            )}
                            Save overlay
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

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
    availablePixels,
}: OverlaysPageProps) {
    return (
        <>
            <Head title="CTA overlays" />

            <div className="flex flex-col gap-6 p-4 sm:p-6">
                <header className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div className="space-y-2">
                        <div className="flex items-center gap-2 text-muted-foreground">
                            <Layers className="size-4" aria-hidden="true" />
                            <span className="text-sm font-medium">
                                Branded interstitials
                            </span>
                        </div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            CTA overlays
                        </h1>
                        <p className="max-w-2xl text-sm text-muted-foreground">
                            Show a LinkPilot-controlled message before a short
                        link delivers its destination.
                        </p>
                    </div>
                    <CreateOverlayDialog availablePixels={availablePixels}>
                        <Button>
                            <Plus data-icon="inline-start" />
                            Create overlay
                        </Button>
                    </CreateOverlayDialog>
                </header>

                {overlays.length === 0 ? (
                    <Empty className="border">
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <Sparkles />
                            </EmptyMedia>
                            <EmptyTitle>No overlays yet</EmptyTitle>
                            <EmptyDescription>
                                Create an interstitial, then assign it to a
                                short link from the links page.
                            </EmptyDescription>
                        </EmptyHeader>
                        <EmptyContent>
                            <CreateOverlayDialog availablePixels={availablePixels}>
                                <Button>
                                    <Plus data-icon="inline-start" />
                                    Create your first overlay
                                </Button>
                            </CreateOverlayDialog>
                        </EmptyContent>
                    </Empty>
                ) : (
                    <div className="grid gap-4 lg:grid-cols-2">
                        {overlays.map((overlay) => (
                            <Card key={overlay.id}>
                                <CardHeader>
                                    <div className="flex flex-wrap items-start justify-between gap-3">
                                        <div className="min-w-0 space-y-1">
                                            <CardTitle>{overlay.name}</CardTitle>
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
                                    <p>
                                        {overlay.trackingPixelIds.length} attached{' '}
                                        {overlay.trackingPixelIds.length === 1
                                            ? 'pixel'
                                            : 'pixels'}
                                    </p>
                                </CardContent>
                                <CardFooter className="flex flex-wrap gap-2">
                                    <EditOverlayDialog
                                        overlay={overlay}
                                        availablePixels={availablePixels}
                                    />
                                    <DeleteOverlayDialog overlay={overlay} />
                                </CardFooter>
                            </Card>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}
