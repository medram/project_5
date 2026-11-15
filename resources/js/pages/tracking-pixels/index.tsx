import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Plus, ShieldCheck, Trash2 } from 'lucide-react';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { destroy, store, update } from '@/routes/tracking-pixels';
import {
    destroy as destroyRule,
    store as storeRule,
} from '@/routes/tracking-pixels/rules';
import type {
    TrackingPixel,
    TrackingPixelProvider,
    TrackingPixelTarget,
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

            <Field className="flex-row items-center">
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

    const planError = (form.errors as Record<string, string | undefined>).plan;

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
                {planError && (
                    <p className="text-sm text-destructive">{planError}</p>
                )}
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

const triggerOptions = [
    { value: 'page_view', label: 'Page view' },
    { value: 'button_click', label: 'Button click' },
    { value: 'lead_submit', label: 'Accepted lead submission' },
] as const;
const NO_TARGET = '__linkpilot_no_target__';

function PixelRules({
    pixel,
    targets,
    editable,
}: {
    pixel: TrackingPixel;
    targets: TrackingPixelTarget[];
    editable: boolean;
}) {
    const [targetKey, setTargetKey] = useState('');
    const form = useForm<{
        target_type: TrackingPixelTarget['type'];
        target_id: number;
        trigger: (typeof triggerOptions)[number]['value'];
    }>({
        target_type: targets[0]?.type ?? 'bio_page',
        target_id: targets[0]?.id ?? 0,
        trigger: 'page_view',
    });
    const selectedTarget = targets.find(
        (option) => `${option.type}:${option.id}` === targetKey,
    );
    const assignmentError = (form.errors as Record<string, string | undefined>)
        .tracking_pixel;

    function addRule(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (!selectedTarget || !pixel.isActive || form.processing) return;
        form.transform((data) => ({
            target_type: selectedTarget.type,
            target_id: selectedTarget.id,
            trigger: data.trigger,
        }));
        form.post(storeRule(pixel.id).url, {
            preserveScroll: true,
        });
    }

    return (
        <section
            aria-label={`${pixel.name} trigger rules`}
            className="space-y-3"
        >
            <h3 className="text-sm font-medium">Trigger rules</h3>
            {pixel.rules.length > 0 ? (
                <ul className="space-y-2">
                    {pixel.rules.map((rule) => {
                        const target = targets.find(
                            (item) =>
                                item.type === rule.targetType &&
                                item.id === rule.targetId,
                        );
                        const triggerLabel = triggerOptions.find(
                            (item) => item.value === rule.trigger,
                        )?.label;
                        return (
                            <li
                                key={rule.id}
                                className="flex flex-wrap items-center justify-between gap-2 rounded-md border p-2 text-sm"
                            >
                                <span>
                                    {target?.name ?? 'Unavailable surface'} ·{' '}
                                    {triggerLabel}
                                </span>
                                {editable && (
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        aria-label={`Remove ${triggerLabel} rule from ${target?.name ?? 'surface'}`}
                                        disabled={form.processing}
                                        onClick={() =>
                                            router.delete(
                                                destroyRule({
                                                    trackingPixel: pixel.id,
                                                    trackingPixelRule: rule.id,
                                                }).url,
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        <Trash2 data-icon="inline-start" />
                                        Remove
                                    </Button>
                                )}
                            </li>
                        );
                    })}
                </ul>
            ) : (
                <p className="text-sm text-muted-foreground">
                    No trigger rules assigned.
                </p>
            )}
            {!pixel.isActive && editable && (
                <p className="text-sm text-muted-foreground">
                    Activate this pixel to add trigger rules. Existing rules may
                    still be removed.
                </p>
            )}
            {editable && targets.length > 0 && (
                <form
                    className="grid gap-2 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto]"
                    onSubmit={addRule}
                >
                    <FieldLabel
                        className="sr-only"
                        htmlFor={`target-${pixel.id}`}
                    >
                        Public surface
                    </FieldLabel>
                    <Select
                        value={targetKey || NO_TARGET}
                        onValueChange={(selectedValue) => {
                            const value =
                                selectedValue === NO_TARGET
                                    ? ''
                                    : selectedValue;
                            const nextTarget = targets.find(
                                (item) => `${item.type}:${item.id}` === value,
                            );
                            setTargetKey(value);
                            if (nextTarget) {
                                form.setData('target_type', nextTarget.type);
                                form.setData('target_id', nextTarget.id);
                            }
                            if (
                                nextTarget?.type !== 'link_page' &&
                                form.data.trigger === 'lead_submit'
                            ) {
                                form.setData('trigger', 'page_view');
                            }
                            form.clearErrors();
                        }}
                        required
                    >
                        <SelectTrigger
                            id={`target-${pixel.id}`}
                            className="h-10 w-full min-w-0"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={NO_TARGET}>
                                Choose a surface
                            </SelectItem>
                            {targets.map((target) => (
                                <SelectItem
                                    key={`${target.type}:${target.id}`}
                                    value={`${target.type}:${target.id}`}
                                >
                                    {target.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <FieldLabel
                        className="sr-only"
                        htmlFor={`trigger-${pixel.id}`}
                    >
                        Trigger
                    </FieldLabel>
                    <Select
                        value={form.data.trigger}
                        onValueChange={(value) => {
                            form.setData(
                                'trigger',
                                value as (typeof triggerOptions)[number]['value'],
                            );
                            form.clearErrors();
                        }}
                    >
                        <SelectTrigger
                            id={`trigger-${pixel.id}`}
                            className="h-10 w-full min-w-0"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {triggerOptions
                                .filter(
                                    (item) =>
                                        item.value !== 'lead_submit' ||
                                        selectedTarget?.type === 'link_page',
                                )
                                .map((item) => (
                                    <SelectItem
                                        key={item.value}
                                        value={item.value}
                                    >
                                        {item.label}
                                    </SelectItem>
                                ))}
                        </SelectContent>
                    </Select>
                    <Button
                        type="submit"
                        disabled={
                            form.processing || !targetKey || !pixel.isActive
                        }
                    >
                        <Plus data-icon="inline-start" />
                        Add rule
                    </Button>
                    {assignmentError && (
                        <FieldError className="sm:col-span-3">
                            {assignmentError}
                        </FieldError>
                    )}
                </form>
            )}
        </section>
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
