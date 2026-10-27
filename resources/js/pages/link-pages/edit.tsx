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
import { LinkPageBlockForm } from '@/components/link-pages/block-form';
import { LinkPageVariantManager } from '@/components/link-pages/variant-manager';
import { LinkPagePresentation } from '@/components/link-pages/presentation';
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
    LinkPageEditorItem,
    LinkPageEditorProps,
    LinkPageItem,
    LinkPageTemplate,
    LinkPageVariant,
} from '@/types/link-pages';
type LinkPageFormData = {
    name: string;
    slug: string;
    title: string;
    description: string;
    is_published: boolean;
    template: LinkPageTemplate;
};

type LinkPageItemFormData = {
    title: string;
    destination_url: string;
    is_active: boolean;
    link_page_variant_id: number;
};

function VariantSelectField({
    id,
    variants,
    value,
    disabled,
    error,
    onChange,
}: {
    id: string;
    variants: LinkPageVariant[];
    value: number;
    disabled: boolean;
    error?: string;
    onChange: (variantId: number) => void;
}) {
    return (
        <Field data-invalid={Boolean(error)}>
            <FieldLabel htmlFor={id}>Variant</FieldLabel>
            <select
                id={id}
                className="h-11 rounded-md border bg-background px-3"
                value={value}
                disabled={disabled}
                aria-invalid={Boolean(error)}
                onChange={(event) =>
                    onChange(Number(event.currentTarget.value))
                }
            >
                {variants.map((variant) => (
                    <option key={variant.id} value={variant.id}>
                        {variant.name} · {variant.weight}%
                        {!variant.isActive ? ' · inactive' : ''}
                    </option>
                ))}
            </select>
            {error && <FieldError>{error}</FieldError>}
        </Field>
    );
}

function ItemForm({
    page,
    item,
    variants,
}: {
    page: LinkPage;
    item: LinkPageItem;
    variants: LinkPageVariant[];
}) {
    const form = useForm<LinkPageItemFormData>({
        title: item.title,
        destination_url: item.destinationUrl,
        is_active: item.isActive,
        link_page_variant_id: item.linkPageVariantId,
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
                    <Badge
                        variant={form.data.is_active ? 'default' : 'secondary'}
                    >
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
                        <Field
                            data-invalid={Boolean(form.errors.destination_url)}
                        >
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
                        <VariantSelectField
                            id={`item-variant-${item.id}`}
                            variants={variants}
                            value={form.data.link_page_variant_id}
                            disabled={form.processing}
                            error={form.errors.link_page_variant_id}
                            onChange={(variantId) =>
                                form.setData('link_page_variant_id', variantId)
                            }
                        />
                        <Field className="flex-row items-center">
                            <Checkbox
                                id={`item-active-${item.id}`}
                                checked={form.data.is_active}
                                onCheckedChange={(checked) =>
                                    form.setData('is_active', checked === true)
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
                        {form.processing && (
                            <Spinner data-icon="inline-start" />
                        )}
                        Save destination
                    </Button>
                </CardFooter>
            </form>
        </Card>
    );
}

function AddItemForm({
    page,
    variants,
    selectedVariantId,
}: {
    page: LinkPage;
    variants: LinkPageVariant[];
    selectedVariantId: number;
}) {
    const form = useForm<LinkPageItemFormData>({
        title: '',
        destination_url: '',
        is_active: true,
        link_page_variant_id: selectedVariantId,
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
                            <FieldLabel htmlFor="new-item-title">
                                Label
                            </FieldLabel>
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
                        <Field
                            data-invalid={Boolean(form.errors.destination_url)}
                        >
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
                        <VariantSelectField
                            id="new-item-variant"
                            variants={variants}
                            value={form.data.link_page_variant_id}
                            disabled={form.processing}
                            error={form.errors.link_page_variant_id}
                            onChange={(variantId) =>
                                form.setData('link_page_variant_id', variantId)
                            }
                        />
                    </FieldGroup>
                </CardContent>
                <CardFooter>
                    <Button type="submit" size="sm" disabled={form.processing}>
                        {form.processing && (
                            <Spinner data-icon="inline-start" />
                        )}
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
    variants,
    selectedVariantId,
    insightsUrl,
    items,
    previewItems,
    canViewLeads,
    leadsUrl,
    privacyUrl,
}: LinkPageEditorProps) {
    const selectedVariant = variants.find(
        (variant) => variant.id === selectedVariantId,
    );

    const form = useForm<LinkPageFormData>({
        name: linkPage.name,
        slug: linkPage.slug,
        title: linkPage.title,
        description: linkPage.description ?? '',
        template: linkPage.template,
        is_published: linkPage.isPublished,
    });

    function save() {
        const url = update(linkPage).url;
        form.patch(
            selectedVariant?.isControl
                ? url
                : `${url}?variant=${selectedVariantId}`,
            { preserveScroll: true },
        );
    }

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        save();
    }

    function moveItem(item: LinkPageEditorItem, direction: -1 | 1) {
        const currentIndex = items.findIndex(
            (candidate) =>
                candidate.id === item.id && candidate.type === item.type,
        );
        const nextIndex = currentIndex + direction;

        if (nextIndex < 0 || nextIndex >= items.length) {
            return;
        }

        const order = items.map((candidate) => ({
            type: candidate.type === 'link' ? 'link' : 'block',
            id: candidate.id,
        }));
        [order[currentIndex], order[nextIndex]] = [
            order[nextIndex],
            order[currentIndex],
        ];

        router.patch(
            reorder(linkPage).url,
            {
                variant_id: selectedVariantId,
                items: order,
            },
            { preserveScroll: true },
        );
    }

    return (
        <>
            <Head title={`Edit ${linkPage.name}`} />

            <div className="flex flex-col gap-6 p-4 sm:p-6">
                <header className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div className="space-y-2">
                        <Button
                            asChild
                            size="sm"
                            variant="ghost"
                            className="-ml-3"
                        >
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
                            Manage the public page, its template, and ordered
                            content. Tracking integrations remain consent-gated.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button asChild variant="outline">
                            <Link href={insightsUrl}>Variant insights</Link>
                        </Button>
                        {canViewLeads && leadsUrl && (
                            <Button asChild variant="outline">
                                <Link href={leadsUrl}>Lead responses</Link>
                            </Button>
                        )}
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
                    </div>
                </header>

                <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(22rem,0.7fr)]">
                    <div className="space-y-4">
                        <Card>
                            <CardHeader>
                                <CardTitle>Page details</CardTitle>
                                <CardDescription>
                                    Published pages are available at /pages/
                                    {linkPage.slug}.
                                </CardDescription>
                            </CardHeader>
                            <form onSubmit={submit}>
                                <CardContent>
                                    <FieldGroup>
                                        <Field
                                            data-invalid={Boolean(
                                                form.errors.name,
                                            )}
                                        >
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
                                        <Field
                                            data-invalid={Boolean(
                                                form.errors.slug,
                                            )}
                                        >
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
                                        <Field
                                            data-invalid={Boolean(
                                                form.errors.title,
                                            )}
                                        >
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
                                        <Field
                                            data-invalid={Boolean(
                                                form.errors.description,
                                            )}
                                        >
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
                                        <Field
                                            data-invalid={Boolean(
                                                form.errors.template,
                                            )}
                                        >
                                            <FieldLabel htmlFor="page-template">
                                                Template
                                            </FieldLabel>
                                            <select
                                                id="page-template"
                                                className="h-11 rounded-md border bg-background px-3"
                                                value={form.data.template}
                                                disabled={form.processing}
                                                aria-invalid={Boolean(
                                                    form.errors.template,
                                                )}
                                                onChange={(event) =>
                                                    form.setData(
                                                        'template',
                                                        event.target
                                                            .value as LinkPageTemplate,
                                                    )
                                                }
                                            >
                                                <option value="standard">
                                                    Standard
                                                </option>
                                                <option value="profile">
                                                    Profile
                                                </option>
                                                <option value="campaign">
                                                    Campaign
                                                </option>
                                            </select>
                                            <FieldDescription>
                                                Change the page layout without
                                                changing its content.
                                            </FieldDescription>
                                            {form.errors.template && (
                                                <FieldError>
                                                    {form.errors.template}
                                                </FieldError>
                                            )}
                                        </Field>
                                        <Field className="flex-row items-center">
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
                                                    Unpublished pages return not
                                                    found to visitors.
                                                </FieldDescription>
                                            </div>
                                        </Field>
                                    </FieldGroup>
                                </CardContent>
                                <CardFooter>
                                    <Button
                                        type="submit"
                                        disabled={form.processing}
                                    >
                                        {form.processing && (
                                            <Spinner data-icon="inline-start" />
                                        )}
                                        <Save data-icon="inline-start" />
                                        Save page
                                    </Button>
                                </CardFooter>
                            </form>
                        </Card>

                        <LinkPageVariantManager
                            page={linkPage}
                            variants={variants}
                            selectedVariantId={selectedVariantId}
                        />

                        <Card>
                            <CardHeader>
                                <CardTitle>Page content</CardTitle>
                                <CardDescription>
                                    Reorder destinations and content blocks
                                    together.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                {items.length === 0 ? (
                                    <p className="rounded-lg border border-dashed p-4 text-sm text-muted-foreground">
                                        Add a destination or content block to
                                        build this page.
                                    </p>
                                ) : (
                                    items.map((item, itemIndex) => {
                                        const label =
                                            item.type === 'link'
                                                ? item.title
                                                : item.type === 'text'
                                                  ? 'Text block'
                                                  : 'Image block';

                                        return (
                                            <div
                                                key={`${item.type}-${item.id}`}
                                                className="space-y-2"
                                            >
                                                <div className="flex gap-2">
                                                    <Button
                                                        type="button"
                                                        size="icon"
                                                        variant="outline"
                                                        aria-label={`Move ${label} up`}
                                                        onClick={() =>
                                                            moveItem(item, -1)
                                                        }
                                                        disabled={
                                                            itemIndex === 0
                                                        }
                                                    >
                                                        <ArrowUp />
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        size="icon"
                                                        variant="outline"
                                                        aria-label={`Move ${label} down`}
                                                        onClick={() =>
                                                            moveItem(item, 1)
                                                        }
                                                        disabled={
                                                            itemIndex ===
                                                            items.length - 1
                                                        }
                                                    >
                                                        <ArrowDown />
                                                    </Button>
                                                </div>
                                                {item.type === 'link' ? (
                                                    <ItemForm
                                                        page={linkPage}
                                                        item={item}
                                                        variants={variants}
                                                    />
                                                ) : (
                                                    <LinkPageBlockForm
                                                        key={`${item.type}-${item.id}`}
                                                        page={linkPage}
                                                        block={item}
                                                        variants={variants}
                                                        selectedVariantId={
                                                            selectedVariantId
                                                        }
                                                    />
                                                )}
                                            </div>
                                        );
                                    })
                                )}
                            </CardContent>
                        </Card>
                        <AddItemForm
                            key={`new-item-${selectedVariantId}`}
                            page={linkPage}
                            variants={variants}
                            selectedVariantId={selectedVariantId}
                        />
                        <LinkPageBlockForm
                            key={`new-block-${selectedVariantId}`}
                            page={linkPage}
                            variants={variants}
                            selectedVariantId={selectedVariantId}
                        />
                    </div>

                    <div className="space-y-4">
                        <Card>
                            <CardHeader>
                                <CardTitle>Page preview</CardTitle>
                                <CardDescription>
                                    Active content uses the selected template.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="mx-auto max-w-sm rounded-[2rem] border bg-muted p-3">
                                    <div className="max-h-[40rem] overflow-y-auto rounded-[1.5rem] bg-background p-4">
                                        <LinkPagePresentation
                                            template={form.data.template}
                                            title={form.data.title}
                                            description={
                                                form.data.description || null
                                            }
                                            items={previewItems}
                                            privacyUrl={privacyUrl}
                                            preview
                                        />
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </>
    );
}
