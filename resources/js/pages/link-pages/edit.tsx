import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowLeft,
    ArrowUp,
    ExternalLink,
    GripVertical,
    Plus,
    Save,
    Trash2,
} from 'lucide-react';
import { type FormEvent } from 'react';
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
    Field,
    FieldDescription,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { index, update } from '@/routes/link-pages';
import {
    destroy as destroyItem,
    reorder,
    store as storeItem,
    update as updateItem,
} from '@/routes/link-pages/items';
import type {
    LinkPage,
    LinkPageEditorProps,
    LinkPageItem,
    TrackingPixel,
} from '@/types/link-pages';

type LinkPageFormData = {
    name: string;
    slug: string;
    title: string;
    description: string;
    is_published: boolean;
    tracking_pixel_ids: number[];
};

type LinkPageItemFormData = {
    title: string;
    destination_url: string;
    is_active: boolean;
};

function providerLabel(pixel: TrackingPixel): string {
    return pixel.provider === 'meta_pixel' ? 'Meta Pixel' : 'Google Analytics';
}

function ItemForm({
    page,
    item,
}: {
    page: LinkPage;
    item: LinkPageItem;
}) {
    const form = useForm<LinkPageItemFormData>({
        title: item.title,
        destination_url: item.destinationUrl,
        is_active: item.isActive,
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.patch(updateItem([page, item]).url, { preserveScroll: true });
    }

    function remove() {
        router.delete(destroyItem([page, item]).url, { preserveScroll: true });
    }

    return (
        <Card>
            <CardHeader className="gap-2">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <CardTitle className="flex items-center gap-2 text-base">
                        <GripVertical
                            className="size-4 text-muted-foreground"
                            aria-hidden="true"
                        />
                        Destination {item.position + 1}
                    </CardTitle>
                    <Badge variant={form.data.is_active ? 'default' : 'secondary'}>
                        {form.data.is_active ? 'Active' : 'Hidden'}
                    </Badge>
                </div>
            </CardHeader>
            <form onSubmit={submit}>
                <CardContent>
                    <FieldGroup>
                        <Field data-invalid={Boolean(form.errors.title)}>
                            <FieldLabel htmlFor={`item-title-${item.id}`}>
                                Label
                            </FieldLabel>
                            <Input
                                id={`item-title-${item.id}`}
                                value={form.data.title}
                                onChange={(event) =>
                                    form.setData('title', event.target.value)
                                }
                                maxLength={100}
                                disabled={form.processing}
                                aria-invalid={Boolean(form.errors.title)}
                            />
                            {form.errors.title && (
                                <FieldError>{form.errors.title}</FieldError>
                            )}
                        </Field>
                        <Field data-invalid={Boolean(form.errors.destination_url)}>
                            <FieldLabel htmlFor={`item-url-${item.id}`}>
                                Destination URL
                            </FieldLabel>
                            <Input
                                id={`item-url-${item.id}`}
                                type="url"
                                value={form.data.destination_url}
                                onChange={(event) =>
                                    form.setData(
                                        'destination_url',
                                        event.target.value,
                                    )
                                }
                                maxLength={2048}
                                disabled={form.processing}
                                aria-invalid={Boolean(
                                    form.errors.destination_url,
                                )}
                            />
                            {form.errors.destination_url && (
                                <FieldError>
                                    {form.errors.destination_url}
                                </FieldError>
                            )}
                        </Field>
                        <Field orientation="horizontal">
                            <Checkbox
                                id={`item-active-${item.id}`}
                                checked={form.data.is_active}
                                onCheckedChange={(checked) =>
                                    form.setData(
                                        'is_active',
                                        checked === true,
                                    )
                                }
                                disabled={form.processing}
                            />
                            <div className="grid gap-1.5 leading-none">
                                <FieldLabel htmlFor={`item-active-${item.id}`}>
                                    Show on the public page
                                </FieldLabel>
                                <FieldDescription>
                                    Hidden destinations stay private and cannot
                                    receive visitor clicks.
                                </FieldDescription>
                            </div>
                        </Field>
                    </FieldGroup>
                </CardContent>
                <CardFooter className="flex flex-wrap justify-between gap-2">
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        onClick={remove}
                        disabled={form.processing}
                    >
                        <Trash2 data-icon="inline-start" />
                        Remove
                    </Button>
                    <Button type="submit" size="sm" disabled={form.processing}>
                        {form.processing && <Spinner data-icon="inline-start" />}
                        Save destination
                    </Button>
                </CardFooter>
            </form>
        </Card>
    );
}

function AddItemForm({ page }: { page: LinkPage }) {
    const form = useForm<LinkPageItemFormData>({
        title: '',
        destination_url: '',
        is_active: true,
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post(storeItem(page).url, {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">Add destination</CardTitle>
                <CardDescription>
                    Each active destination is delivered through a LinkPilot
                    redirect so consented interactions can be measured safely.
                </CardDescription>
            </CardHeader>
            <form onSubmit={submit}>
                <CardContent>
                    <FieldGroup>
                        <Field data-invalid={Boolean(form.errors.title)}>
                            <FieldLabel htmlFor="new-item-title">Label</FieldLabel>
                            <Input
                                id="new-item-title"
                                value={form.data.title}
                                onChange={(event) =>
                                    form.setData('title', event.target.value)
                                }
                                placeholder="Read the announcement"
                                maxLength={100}
                                disabled={form.processing}
                                aria-invalid={Boolean(form.errors.title)}
                            />
                            {form.errors.title && (
                                <FieldError>{form.errors.title}</FieldError>
                            )}
                        </Field>
                        <Field data-invalid={Boolean(form.errors.destination_url)}>
                            <FieldLabel htmlFor="new-item-url">
                                Destination URL
                            </FieldLabel>
                            <Input
                                id="new-item-url"
                                type="url"
                                value={form.data.destination_url}
                                onChange={(event) =>
                                    form.setData(
                                        'destination_url',
                                        event.target.value,
                                    )
                                }
                                placeholder="https://example.com/announcement"
                                maxLength={2048}
                                disabled={form.processing}
                                aria-invalid={Boolean(
                                    form.errors.destination_url,
                                )}
                            />
                            {form.errors.destination_url && (
                                <FieldError>
                                    {form.errors.destination_url}
                                </FieldError>
                            )}
                        </Field>
                    </FieldGroup>
                </CardContent>
                <CardFooter>
                    <Button type="submit" size="sm" disabled={form.processing}>
                        {form.processing && <Spinner data-icon="inline-start" />}
                        <Plus data-icon="inline-start" />
                        Add destination
                    </Button>
                </CardFooter>
            </form>
        </Card>
    );
}

export default function LinkPageEditor({
    linkPage,
    items,
    availablePixels,
}: LinkPageEditorProps) {
    const form = useForm<LinkPageFormData>({
        name: linkPage.name,
        slug: linkPage.slug,
        title: linkPage.title,
        description: linkPage.description ?? '',
        is_published: linkPage.isPublished,
        tracking_pixel_ids: linkPage.trackingPixelIds.filter((pixelId) =>
            availablePixels.some(
                (pixel) => pixel.id === pixelId && pixel.isActive,
            ),
        ),
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.patch(update(linkPage).url, { preserveScroll: true });
    }

    function togglePixel(pixel: TrackingPixel) {
        if (!pixel.isActive) {
            return;
        }

        form.setData(
            'tracking_pixel_ids',
            form.data.tracking_pixel_ids.includes(pixel.id)
                ? form.data.tracking_pixel_ids.filter((id) => id !== pixel.id)
                : [...form.data.tracking_pixel_ids, pixel.id],
        );
    }

    function moveItem(item: LinkPageItem, direction: -1 | 1) {
        const currentIndex = items.findIndex((candidate) => candidate.id === item.id);
        const nextIndex = currentIndex + direction;

        if (nextIndex < 0 || nextIndex >= items.length) {
            return;
        }

        const itemIds = items.map((candidate) => candidate.id);
        [itemIds[currentIndex], itemIds[nextIndex]] = [
            itemIds[nextIndex],
            itemIds[currentIndex],
        ];

        router.patch(
            reorder(linkPage).url,
            { item_ids: itemIds },
            { preserveScroll: true },
        );
    }

    return (
        <>
            <Head title={`Edit ${linkPage.name}`} />

            <div className="flex flex-col gap-6 p-4 sm:p-6">
                <header className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div className="space-y-2">
                        <Button asChild size="sm" variant="ghost" className="-ml-3">
                            <Link href={index().url}>
                                <ArrowLeft data-icon="inline-start" />
                                Link pages
                            </Link>
                        </Button>
                        <div className="flex flex-wrap items-center gap-2">
                            <h1 className="text-2xl font-semibold tracking-tight">
                                {linkPage.name}
                            </h1>
                            <Badge
                                variant={
                                    form.data.is_published
                                        ? 'default'
                                        : 'secondary'
                                }
                            >
                                {form.data.is_published ? 'Published' : 'Draft'}
                            </Badge>
                        </div>
                        <p className="max-w-2xl text-sm text-muted-foreground">
                            Control the public page, its destinations, and safe
                            consent-gated tracking integrations.
                        </p>
                    </div>
                    {linkPage.isPublished && (
                        <Button asChild variant="outline">
                            <a
                                href={linkPage.publicUrl}
                                target="_blank"
                                rel="noreferrer"
                            >
                                <ExternalLink data-icon="inline-start" />
                                Open public page
                            </a>
                        </Button>
                    )}
                </header>

                <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(22rem,0.7fr)]">
                    <div className="space-y-4">
                        <Card>
                            <CardHeader>
                                <CardTitle>Page details</CardTitle>
                                <CardDescription>
                                    Published pages are available at /pages/{linkPage.slug}.
                                </CardDescription>
                            </CardHeader>
                            <form onSubmit={submit}>
                                <CardContent>
                                    <FieldGroup>
                                        <Field data-invalid={Boolean(form.errors.name)}>
                                            <FieldLabel htmlFor="page-name">
                                                Internal name
                                            </FieldLabel>
                                            <Input
                                                id="page-name"
                                                value={form.data.name}
                                                onChange={(event) =>
                                                    form.setData(
                                                        'name',
                                                        event.target.value,
                                                    )
                                                }
                                                maxLength={100}
                                                disabled={form.processing}
                                                aria-invalid={Boolean(
                                                    form.errors.name,
                                                )}
                                            />
                                            {form.errors.name && (
                                                <FieldError>
                                                    {form.errors.name}
                                                </FieldError>
                                            )}
                                        </Field>
                                        <Field data-invalid={Boolean(form.errors.slug)}>
                                            <FieldLabel htmlFor="page-slug">
                                                Public slug
                                            </FieldLabel>
                                            <Input
                                                id="page-slug"
                                                value={form.data.slug}
                                                onChange={(event) =>
                                                    form.setData(
                                                        'slug',
                                                        event.target.value.toLowerCase(),
                                                    )
                                                }
                                                maxLength={32}
                                                disabled={form.processing}
                                                aria-invalid={Boolean(
                                                    form.errors.slug,
                                                )}
                                            />
                                            {form.errors.slug && (
                                                <FieldError>
                                                    {form.errors.slug}
                                                </FieldError>
                                            )}
                                        </Field>
                                        <Field data-invalid={Boolean(form.errors.title)}>
                                            <FieldLabel htmlFor="page-title">
                                                Public title
                                            </FieldLabel>
                                            <Input
                                                id="page-title"
                                                value={form.data.title}
                                                onChange={(event) =>
                                                    form.setData(
                                                        'title',
                                                        event.target.value,
                                                    )
                                                }
                                                maxLength={160}
                                                disabled={form.processing}
                                                aria-invalid={Boolean(
                                                    form.errors.title,
                                                )}
                                            />
                                            {form.errors.title && (
                                                <FieldError>
                                                    {form.errors.title}
                                                </FieldError>
                                            )}
                                        </Field>
                                        <Field data-invalid={Boolean(form.errors.description)}>
                                            <FieldLabel htmlFor="page-description">
                                                Description
                                            </FieldLabel>
                                            <Textarea
                                                id="page-description"
                                                value={form.data.description}
                                                onChange={(event) =>
                                                    form.setData(
                                                        'description',
                                                        event.target.value,
                                                    )
                                                }
                                                maxLength={500}
                                                disabled={form.processing}
                                                aria-invalid={Boolean(
                                                    form.errors.description,
                                                )}
                                            />
                                            {form.errors.description && (
                                                <FieldError>
                                                    {form.errors.description}
                                                </FieldError>
                                            )}
                                        </Field>
                                        <Field orientation="horizontal">
                                            <Checkbox
                                                id="page-published"
                                                checked={form.data.is_published}
                                                onCheckedChange={(checked) =>
                                                    form.setData(
                                                        'is_published',
                                                        checked === true,
                                                    )
                                                }
                                                disabled={form.processing}
                                            />
                                            <div className="grid gap-1.5 leading-none">
                                                <FieldLabel htmlFor="page-published">
                                                    Publish page
                                                </FieldLabel>
                                                <FieldDescription>
                                                    Unpublished pages return not found to visitors.
                                                </FieldDescription>
                                            </div>
                                        </Field>
                                    </FieldGroup>
                                </CardContent>
                                <CardFooter>
                                    <Button type="submit" disabled={form.processing}>
                                        {form.processing && (
                                            <Spinner data-icon="inline-start" />
                                        )}
                                        <Save data-icon="inline-start" />
                                        Save page
                                    </Button>
                                </CardFooter>
                            </form>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Destinations</CardTitle>
                                <CardDescription>
                                    Visitors see active destinations in this order.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                {items.length === 0 ? (
                                    <p className="rounded-lg border border-dashed p-4 text-sm text-muted-foreground">
                                        Add the first destination to make this
                                        campaign useful to visitors.
                                    </p>
                                ) : (
                                    items.map((item, itemIndex) => (
                                        <div key={item.id} className="space-y-2">
                                            <div className="flex gap-2">
                                                <Button
                                                    type="button"
                                                    size="icon"
                                                    variant="outline"
                                                    aria-label={`Move ${item.title} up`}
                                                    onClick={() => moveItem(item, -1)}
                                                    disabled={itemIndex === 0}
                                                >
                                                    <ArrowUp />
                                                </Button>
                                                <Button
                                                    type="button"
                                                    size="icon"
                                                    variant="outline"
                                                    aria-label={`Move ${item.title} down`}
                                                    onClick={() => moveItem(item, 1)}
                                                    disabled={itemIndex === items.length - 1}
                                                >
                                                    <ArrowDown />
                                                </Button>
                                            </div>
                                            <ItemForm page={linkPage} item={item} />
                                        </div>
                                    ))
                                )}
                            </CardContent>
                        </Card>
                        <AddItemForm page={linkPage} />
                    </div>

                    <Card className="h-fit">
                        <CardHeader>
                            <CardTitle>Tracking pixels</CardTitle>
                            <CardDescription>
                                Attach up to five active account-owned pixels.
                                LinkPilot sends them only after visitor consent.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {availablePixels.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    Create a pixel first to attach it here.
                                </p>
                            ) : (
                                availablePixels.map((pixel) => {
                                    const attached = form.data.tracking_pixel_ids.includes(
                                        pixel.id,
                                    );

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
                                                    (form.data.tracking_pixel_ids.length >= 5 &&
                                                        !attached) ||
                                                    form.processing
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
                                                    {providerLabel(pixel)} · {pixel.providerId}
                                                </span>
                                            </span>
                                        </label>
                                    );
                                })
                            )}
                            {form.errors.tracking_pixel_ids && (
                                <FieldError>
                                    {form.errors.tracking_pixel_ids}
                                </FieldError>
                            )}
                        </CardContent>
                        <CardFooter>
                            <Button onClick={submit} disabled={form.processing}>
                                {form.processing && (
                                    <Spinner data-icon="inline-start" />
                                )}
                                <Save data-icon="inline-start" />
                                Save pixel selection
                            </Button>
                        </CardFooter>
                    </Card>
                </div>
            </div>
        </>
    );
}
