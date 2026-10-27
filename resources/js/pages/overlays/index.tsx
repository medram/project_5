import { Head, router, useForm } from '@inertiajs/react';
import { Layers, Pencil, Plus, Sparkles, Trash2 } from 'lucide-react';
import { type FormEvent, type ReactNode, useState } from 'react';
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
import { destroy, store, update } from '@/routes/overlays';
import type { LinkOverlay, OverlaysPageProps } from '@/types/overlays';

type OverlayFormData = {
    name: string;
    heading: string;
    body: string;
    cta_label: string;
    is_active: boolean;
    starts_at: string;
    expires_at: string;
    display_delay_seconds: number;
};

type OverlayFormFieldsProps = {
    data: OverlayFormData;
    errors: Partial<Record<keyof OverlayFormData, string>>;
    processing: boolean;
    setData: (
        key: keyof OverlayFormData,
        value: string | boolean | number,
    ) => void;
};

function utcDateTimeInput(value: string | null): string {
    return value ? new Date(value).toISOString().slice(0, 16) : '';
}

function withUtcSchedule(data: OverlayFormData): OverlayFormData {
    return {
        ...data,
        starts_at: data.starts_at ? `${data.starts_at}:00Z` : '',
        expires_at: data.expires_at ? `${data.expires_at}:00Z` : '',
    };
}

function OverlayFormFields({
    data,
    errors,
    processing,
    setData,
}: OverlayFormFieldsProps) {
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

            <Field className="flex-row items-center">
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

            <div className="grid gap-5 sm:grid-cols-2">
                <Field data-invalid={Boolean(errors.starts_at)}>
                    <FieldLabel htmlFor="overlay-starts-at">
                        Starts at (UTC)
                    </FieldLabel>
                    <Input
                        id="overlay-starts-at"
                        type="datetime-local"
                        value={data.starts_at}
                        onChange={(event) =>
                            setData('starts_at', event.target.value)
                        }
                        disabled={processing}
                        aria-invalid={Boolean(errors.starts_at)}
                    />
                    <FieldDescription>
                        The overlay is available at this exact time. Leave empty
                        to start immediately.
                    </FieldDescription>
                    {errors.starts_at && (
                        <FieldError>{errors.starts_at}</FieldError>
                    )}
                </Field>

                <Field data-invalid={Boolean(errors.expires_at)}>
                    <FieldLabel htmlFor="overlay-expires-at">
                        Expires at (UTC)
                    </FieldLabel>
                    <Input
                        id="overlay-expires-at"
                        type="datetime-local"
                        value={data.expires_at}
                        onChange={(event) =>
                            setData('expires_at', event.target.value)
                        }
                        disabled={processing}
                        aria-invalid={Boolean(errors.expires_at)}
                    />
                    <FieldDescription>
                        The overlay is unavailable at this exact time. Leave
                        empty for no expiry.
                    </FieldDescription>
                    {errors.expires_at && (
                        <FieldError>{errors.expires_at}</FieldError>
                    )}
                </Field>
            </div>

            <Field data-invalid={Boolean(errors.display_delay_seconds)}>
                <FieldLabel htmlFor="overlay-display-delay">
                    Continue delay (seconds)
                </FieldLabel>
                <Input
                    id="overlay-display-delay"
                    type="number"
                    min={0}
                    max={60}
                    step={1}
                    value={data.display_delay_seconds}
                    onChange={(event) =>
                        setData(
                            'display_delay_seconds',
                            event.target.value === ''
                                ? 0
                                : event.target.valueAsNumber,
                        )
                    }
                    disabled={processing}
                    aria-invalid={Boolean(errors.display_delay_seconds)}
                />
                <FieldDescription>
                    Visitors wait for this LinkPilot-enforced countdown before
                    the button becomes available (0–60 seconds).
                </FieldDescription>
                {errors.display_delay_seconds && (
                    <FieldError>{errors.display_delay_seconds}</FieldError>
                )}
            </Field>
        </FieldGroup>
    );
}

function CreateOverlayDialog({ children }: { children: ReactNode }) {
    const [open, setOpen] = useState(false);
    const form = useForm<OverlayFormData>({
        name: '',
        heading: '',
        body: '',
        cta_label: 'Continue',
        is_active: true,
        starts_at: '',
        expires_at: '',
        display_delay_seconds: 0,
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.transform(withUtcSchedule);
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
            <DialogContent className="max-h-[calc(100dvh-2rem)] overflow-y-auto sm:max-w-lg">
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

function EditOverlayDialog({ overlay }: { overlay: LinkOverlay }) {
    const [open, setOpen] = useState(false);
    const form = useForm<OverlayFormData>({
        name: overlay.name,
        heading: overlay.heading,
        body: overlay.body ?? '',
        cta_label: overlay.ctaLabel,
        is_active: overlay.isActive,
        starts_at: utcDateTimeInput(overlay.startsAt),
        expires_at: utcDateTimeInput(overlay.expiresAt),
        display_delay_seconds: overlay.displayDelaySeconds,
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.transform(withUtcSchedule);
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
            <DialogContent className="max-h-[calc(100dvh-2rem)] overflow-y-auto sm:max-w-lg">
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
    canManageIntegrations,
}: OverlaysPageProps) {
    return (
        <>
            <Head title="CTA overlays" />

            <div className="flex flex-col gap-6 p-4 sm:p-6">
                <PageHeader
                    title="CTA overlays"
                    eyebrow="Branded interstitials"
                    icon={Layers}
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
