import { Head, router, useForm } from '@inertiajs/react';
import { Activity, Pencil, Plus, ShieldCheck, Trash2 } from 'lucide-react';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import {
    destroy,
    index,
    store,
    update,
} from '@/routes/tracking-pixels';
import type {
    TrackingPixel,
    TrackingPixelProvider,
    TrackingPixelsPageProps,
} from '@/types/link-pages';

type PixelFormData = {
    name: string;
    provider: TrackingPixelProvider;
    provider_id: string;
    is_active: boolean;
};

type PixelFormFieldsProps = {
    data: PixelFormData;
    errors: Partial<Record<keyof PixelFormData, string>>;
    processing: boolean;
    setData: (key: keyof PixelFormData, value: string | boolean) => void;
};

function providerLabel(provider: TrackingPixelProvider): string {
    return provider === 'meta_pixel' ? 'Meta Pixel' : 'Google Analytics';
}

function PixelFormFields({
    data,
    errors,
    processing,
    setData,
}: PixelFormFieldsProps) {
    const isMeta = data.provider === 'meta_pixel';

    return (
        <FieldGroup>
            <Field data-invalid={Boolean(errors.name)}>
                <FieldLabel htmlFor="pixel-name">Name</FieldLabel>
                <Input
                    id="pixel-name"
                    value={data.name}
                    onChange={(event) => setData('name', event.target.value)}
                    maxLength={100}
                    autoComplete="off"
                    disabled={processing}
                    aria-invalid={Boolean(errors.name)}
                />
                {errors.name && <FieldError>{errors.name}</FieldError>}
            </Field>

            <Field data-invalid={Boolean(errors.provider)}>
                <FieldLabel htmlFor="pixel-provider">Provider</FieldLabel>
                <Select
                    value={data.provider}
                    onValueChange={(value: TrackingPixelProvider) =>
                        setData('provider', value)
                    }
                    disabled={processing}
                >
                    <SelectTrigger
                        id="pixel-provider"
                        aria-invalid={Boolean(errors.provider)}
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="meta_pixel">Meta Pixel</SelectItem>
                        <SelectItem value="google_analytics">
                            Google Analytics (GA4)
                        </SelectItem>
                    </SelectContent>
                </Select>
                <FieldDescription>
                    Only fixed provider integrations are supported. Google Tag
                    Manager and custom scripts are intentionally unavailable.
                </FieldDescription>
                {errors.provider && <FieldError>{errors.provider}</FieldError>}
            </Field>

            <Field data-invalid={Boolean(errors.provider_id)}>
                <FieldLabel htmlFor="pixel-provider-id">
                    {isMeta ? 'Meta Pixel ID' : 'GA4 measurement ID'}
                </FieldLabel>
                <Input
                    id="pixel-provider-id"
                    value={data.provider_id}
                    onChange={(event) =>
                        setData(
                            'provider_id',
                            isMeta
                                ? event.target.value
                                : event.target.value.toUpperCase(),
                        )
                    }
                    placeholder={isMeta ? '123456789012345' : 'G-AB12CD34'}
                    maxLength={32}
                    autoComplete="off"
                    disabled={processing}
                    aria-invalid={Boolean(errors.provider_id)}
                />
                <FieldDescription>
                    {isMeta
                        ? 'Use the numeric ID from Meta Events Manager.'
                        : 'Use a GA4 ID beginning with G-.'}
                </FieldDescription>
                {errors.provider_id && (
                    <FieldError>{errors.provider_id}</FieldError>
                )}
            </Field>

            <Field orientation="horizontal">
                <Checkbox
                    id="pixel-active"
                    checked={data.is_active}
                    onCheckedChange={(checked) =>
                        setData('is_active', checked === true)
                    }
                    disabled={processing}
                />
                <div className="grid gap-1.5 leading-none">
                    <FieldLabel htmlFor="pixel-active">Active</FieldLabel>
                    <FieldDescription>
                        Active pixels can be attached to public LinkPilot pages.
                    </FieldDescription>
                </div>
            </Field>
        </FieldGroup>
    );
}

function CreatePixelDialog({ children }: { children: ReactNode }) {
    const [open, setOpen] = useState(false);
    const form = useForm<PixelFormData>({
        name: '',
        provider: 'meta_pixel',
        provider_id: '',
        is_active: true,
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post(store().url, {
            preserveScroll: true,
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
                    <DialogTitle>Add tracking pixel</DialogTitle>
                    <DialogDescription>
                        LinkPilot renders fixed Meta or GA4 integrations only
                        after a visitor grants consent.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit}>
                    <PixelFormFields
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
                            Add pixel
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function EditPixelDialog({ pixel }: { pixel: TrackingPixel }) {
    const [open, setOpen] = useState(false);
    const form = useForm<PixelFormData>({
        name: pixel.name,
        provider: pixel.provider,
        provider_id: pixel.providerId,
        is_active: pixel.isActive,
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.patch(update(pixel).url, {
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
                    <DialogTitle>Edit {pixel.name}</DialogTitle>
                    <DialogDescription>
                        Deactivating a pixel stops it from rendering on new
                        public page visits.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit}>
                    <PixelFormFields
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
                            Save pixel
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
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
                        Link pages using this pixel stop loading it immediately.
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
}: TrackingPixelsPageProps) {
    return (
        <>
            <Head title="Tracking pixels" />

            <div className="flex flex-col gap-6 p-4 sm:p-6">
                <header className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div className="space-y-2">
                        <div className="flex items-center gap-2 text-muted-foreground">
                            <Activity className="size-4" aria-hidden="true" />
                            <span className="text-sm font-medium">
                                Consent-gated integrations
                            </span>
                        </div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Tracking pixels
                        </h1>
                        <p className="max-w-2xl text-sm text-muted-foreground">
                            Add fixed Meta Pixel or Google Analytics integrations
                            for LinkPilot-controlled public pages.
                        </p>
                    </div>
                    <CreatePixelDialog>
                        <Button>
                            <Plus data-icon="inline-start" />
                            Add pixel
                        </Button>
                    </CreatePixelDialog>
                </header>

                {pixels.length === 0 ? (
                    <Empty className="border">
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <ShieldCheck />
                            </EmptyMedia>
                            <EmptyTitle>No tracking pixels yet</EmptyTitle>
                            <EmptyDescription>
                                Create a fixed provider integration, then attach
                                it to a published link page.
                            </EmptyDescription>
                        </EmptyHeader>
                        <EmptyContent>
                            <CreatePixelDialog>
                                <Button>
                                    <Plus data-icon="inline-start" />
                                    Add your first pixel
                                </Button>
                            </CreatePixelDialog>
                        </EmptyContent>
                    </Empty>
                ) : (
                    <div className="grid gap-4 lg:grid-cols-2">
                        {pixels.map((pixel) => (
                            <Card key={pixel.id}>
                                <CardHeader>
                                    <div className="flex flex-wrap items-start justify-between gap-3">
                                        <div className="space-y-1">
                                            <CardTitle>{pixel.name}</CardTitle>
                                            <CardDescription>
                                                {providerLabel(pixel.provider)} ·{' '}
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
                                <CardContent>
                                    <p className="text-sm text-muted-foreground">
                                        {pixel.linkPagesCount ?? 0} attached{' '}
                                        {pixel.linkPagesCount === 1
                                            ? 'link page'
                                            : 'link pages'}
                                    </p>
                                </CardContent>
                                <CardFooter className="flex flex-wrap gap-2">
                                    <EditPixelDialog pixel={pixel} />
                                    <DeletePixelDialog pixel={pixel} />
                                </CardFooter>
                            </Card>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}
