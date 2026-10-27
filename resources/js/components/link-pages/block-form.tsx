import { router } from '@inertiajs/react';
import { Plus, Save, Trash2 } from 'lucide-react';
import { type FormEvent, useState } from 'react';
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
import { FieldError } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import {
    destroy as destroyBlock,
    store as storeBlock,
    update as updateBlock,
} from '@/routes/link-pages/blocks';
import type {
    LinkPage,
    LinkPageEditorBlock,
    LinkPageFormField,
    LinkPageVariant,
} from '@/types/link-pages';

type FormFieldFormData = LinkPageFormField & { optionsText: string };

type BlockFormData = {
    type: 'text' | 'image' | 'form' | 'file';
    text: string;
    image: File | null;
    file: File | null;
    alt_text: string;
    fields: FormFieldFormData[];
    is_active: boolean;
    starts_at: string;
    ends_at: string;
};

const initialFormField = (
    existing: FormFieldFormData[] = [],
): FormFieldFormData => {
    let index = 1;
    while (existing.some((field) => field.id === `field_${index}`)) index++;

    return {
        id: `field_${index}`,
        label: index === 1 ? 'Name' : '',
        type: 'text',
        required: index === 1,
        optionsText: '',
    };
};

const initialBlock = (): BlockFormData => ({
    type: 'text',
    text: '',
    image: null,
    file: null,
    alt_text: '',
    fields: [],
    is_active: true,
    starts_at: '',
    ends_at: '',
});

function localDate(value: string | null): string {
    if (!value) return '';
    const date = new Date(value);
    return Number.isNaN(date.valueOf())
        ? ''
        : new Date(date.valueOf() - date.getTimezoneOffset() * 60000)
              .toISOString()
              .slice(0, 16);
}

function utcDate(value: string): string {
    return value ? new Date(value).toISOString() : '';
}

function initialValue(block?: LinkPageEditorBlock): BlockFormData {
    if (!block) return initialBlock();

    return {
        type: block.type,
        text: block.type === 'text' ? block.text : '',
        image: null,
        file: null,
        alt_text: block.type === 'image' ? block.altText : '',
        fields:
            block.type === 'form'
                ? block.fields.map((field) => ({
                      ...field,
                      optionsText: field.options?.join('\n') ?? '',
                  }))
                : [],
        is_active: block.isActive,
        starts_at: block.startsAt ?? '',
        ends_at: block.endsAt ?? '',
    };
}

export function LinkPageBlockForm({
    page,
    block,
    variants,
    selectedVariantId,
}: {
    page: LinkPage;
    block?: LinkPageEditorBlock;
    variants: LinkPageVariant[];
    selectedVariantId: number;
}) {
    const [value, setValue] = useState(() => initialValue(block));
    const [variantId, setVariantId] = useState(
        block?.linkPageVariantId ?? selectedVariantId,
    );
    const [busy, setBusy] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    function set<K extends keyof BlockFormData>(
        key: K,
        next: BlockFormData[K],
    ) {
        setValue((current) => ({ ...current, [key]: next }));
        setErrors((current) => {
            if (!(key in current) && !(key === 'image' && 'file' in current)) {
                return current;
            }
            const nextErrors = { ...current };
            delete nextErrors[key];
            if (key === 'image' || key === 'file') delete nextErrors.file;
            return nextErrors;
        });
    }
    function changeField(index: number, updates: Partial<FormFieldFormData>) {
        setValue((current) => ({
            ...current,
            fields: current.fields.map((field, fieldIndex) =>
                fieldIndex === index ? { ...field, ...updates } : field,
            ),
        }));
        setErrors({});
    }

    function addField() {
        setValue((current) => ({
            ...current,
            fields: [...current.fields, initialFormField(current.fields)],
        }));
        setErrors({});
    }

    function removeField(index: number) {
        setValue((current) => ({
            ...current,
            fields: current.fields.filter(
                (_, fieldIndex) => fieldIndex !== index,
            ),
        }));
        setErrors({});
    }

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        setBusy(true);

        const data = new FormData();
        data.append('type', value.type);
        data.append('is_active', value.is_active ? '1' : '0');
        data.append('starts_at', value.starts_at);
        data.append('ends_at', value.ends_at);
        data.append('link_page_variant_id', String(variantId));
        if (value.type === 'text') {
            data.append('text', value.text);
        } else if (value.type === 'image') {
            data.append('alt_text', value.alt_text);
            if (value.image) data.append('image', value.image);
        } else if (value.type === 'file') {
            if (value.file) data.append('file', value.file);
        } else {
            value.fields.forEach((field, index) => {
                data.append(`fields[${index}][id]`, field.id);
                data.append(`fields[${index}][label]`, field.label);
                data.append(`fields[${index}][type]`, field.type);
                data.append(
                    `fields[${index}][required]`,
                    field.required ? '1' : '0',
                );
                if (field.type === 'select') {
                    field.optionsText
                        .split('\n')
                        .map((option) => option.trim())
                        .filter(Boolean)
                        .forEach((option, optionIndex) => {
                            data.append(
                                `fields[${index}][options][${optionIndex}]`,
                                option,
                            );
                        });
                }
            });
        }

        const options = {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                setErrors({});
                setValue((current) =>
                    block
                        ? { ...current, image: null, file: null }
                        : initialBlock(),
                );
            },
            onError: setErrors,
            onFinish: () => setBusy(false),
        };

        if (block) {
            data.append('_method', 'patch');
            router.post(
                updateBlock({ linkPage: page.id, linkPageBlock: block.id }).url,
                data,
                options,
            );
        } else {
            router.post(storeBlock(page.id).url, data, options);
        }
    }

    function remove() {
        if (!block) return;
        router.delete(
            destroyBlock({ linkPage: page.id, linkPageBlock: block.id }).url,
            { preserveScroll: true },
        );
    }

    const title = block
        ? block.type === 'text'
            ? 'Text block'
            : block.type === 'image'
              ? 'Image block'
              : block.type === 'file'
                ? 'File block'
                : 'Lead form'
        : 'Add content block';

    const imageError = errors.image ?? errors.file;
    const fileError = errors.file;

    return (
        <Card>
            <CardHeader className="gap-2">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <CardTitle className="text-base">{title}</CardTitle>
                    {block && (
                        <Badge
                            variant={value.is_active ? 'default' : 'secondary'}
                        >
                            {value.is_active ? 'Active' : 'Hidden'}
                        </Badge>
                    )}
                </div>
                {!block && (
                    <CardDescription>
                        Add text, an image, a PDF download, or a consented lead
                        form.
                    </CardDescription>
                )}
            </CardHeader>
            <form onSubmit={submit}>
                <CardContent className="space-y-4">
                    <label className="grid gap-1 text-sm">
                        Content type
                        <select
                            aria-invalid={Boolean(errors.type)}
                            className="h-11 rounded-md border bg-background px-3"
                            value={value.type}
                            disabled={busy}
                            onChange={(event) => {
                                const type = event.target
                                    .value as BlockFormData['type'];
                                setErrors({});
                                setValue((current) => ({
                                    ...current,
                                    type,
                                    fields:
                                        type === 'form' &&
                                        current.fields.length === 0
                                            ? [initialFormField()]
                                            : current.fields,
                                }));
                            }}
                        >
                            <option value="text">Text</option>
                            <option value="image">Image</option>
                            <option value="file">PDF file</option>
                            <option value="form">Lead form</option>
                        </select>
                        {errors.type && (
                            <FieldError role="alert">{errors.type}</FieldError>
                        )}
                    </label>

                    <label className="grid gap-1 text-sm">
                        Variant
                        <select
                            aria-invalid={Boolean(errors.link_page_variant_id)}
                            className="h-11 rounded-md border bg-background px-3"
                            value={variantId}
                            disabled={busy}
                            onChange={(event) => {
                                setVariantId(Number(event.currentTarget.value));
                                setErrors((current) => {
                                    const next = { ...current };
                                    delete next.link_page_variant_id;
                                    return next;
                                });
                            }}
                        >
                            {variants.map((variant) => (
                                <option key={variant.id} value={variant.id}>
                                    {variant.name} · {variant.weight}%
                                    {!variant.isActive ? ' · inactive' : ''}
                                </option>
                            ))}
                        </select>
                        {errors.link_page_variant_id && (
                            <FieldError role="alert">
                                {errors.link_page_variant_id}
                            </FieldError>
                        )}
                    </label>

                    {value.type === 'text' ? (
                        <label className="grid gap-1 text-sm">
                            Text
                            <Textarea
                                aria-invalid={Boolean(errors.text)}
                                maxLength={5000}
                                value={value.text}
                                disabled={busy}
                                onChange={(event) =>
                                    set('text', event.target.value)
                                }
                            />
                            {errors.text && (
                                <FieldError role="alert">
                                    {errors.text}
                                </FieldError>
                            )}
                        </label>
                    ) : value.type === 'image' ? (
                        <div className="grid gap-3">
                            {block?.type === 'image' && block.imageUrl && (
                                <img
                                    src={block.imageUrl}
                                    alt={block.altText}
                                    className="max-h-48 w-fit max-w-full rounded-lg object-contain"
                                />
                            )}
                            <label className="grid gap-1 text-sm">
                                Choose image
                                <Input
                                    aria-invalid={Boolean(imageError)}
                                    type="file"
                                    accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                                    disabled={busy}
                                    onChange={(event) =>
                                        set(
                                            'image',
                                            event.target.files?.[0] ?? null,
                                        )
                                    }
                                />
                                {imageError && (
                                    <FieldError role="alert">
                                        {imageError}
                                    </FieldError>
                                )}
                            </label>
                            <label className="grid gap-1 text-sm">
                                Alt text
                                <Input
                                    aria-invalid={Boolean(errors.alt_text)}
                                    maxLength={250}
                                    value={value.alt_text}
                                    disabled={busy}
                                    onChange={(event) =>
                                        set('alt_text', event.target.value)
                                    }
                                />
                                {errors.alt_text && (
                                    <FieldError role="alert">
                                        {errors.alt_text}
                                    </FieldError>
                                )}
                            </label>
                        </div>
                    ) : value.type === 'file' ? (
                        <div className="grid gap-3">
                            {block?.type === 'file' && block.fileName && (
                                <p className="text-sm break-all text-muted-foreground">
                                    Current file: {block.fileName}
                                </p>
                            )}
                            <label className="grid gap-1 text-sm">
                                Choose PDF
                                <Input
                                    aria-invalid={Boolean(fileError)}
                                    type="file"
                                    accept=".pdf,application/pdf"
                                    disabled={busy}
                                    onChange={(event) =>
                                        set(
                                            'file',
                                            event.target.files?.[0] ?? null,
                                        )
                                    }
                                />
                                {fileError && (
                                    <FieldError role="alert">
                                        {fileError}
                                    </FieldError>
                                )}
                            </label>
                            <p className="text-sm text-muted-foreground">
                                PDF files are scanned before visitors can
                                download them.
                            </p>
                        </div>
                    ) : (
                        <div className="space-y-3">
                            <p className="text-sm text-muted-foreground">
                                Choose a small set of fields. Visitors must
                                agree before their responses are saved.
                            </p>
                            {value.fields.map((field, index) => {
                                const labelError =
                                    errors[`fields.${index}.label`];
                                const typeError =
                                    errors[`fields.${index}.type`];
                                const requiredError =
                                    errors[`fields.${index}.required`];
                                const optionsError = Object.entries(
                                    errors,
                                ).find(
                                    ([key]) =>
                                        key === `fields.${index}.options` ||
                                        key.startsWith(
                                            `fields.${index}.options.`,
                                        ),
                                )?.[1];

                                return (
                                    <fieldset
                                        key={field.id}
                                        className="grid gap-3 rounded-lg border p-3 sm:grid-cols-2"
                                    >
                                        <legend className="px-1 text-sm font-medium">
                                            Field {index + 1}
                                        </legend>
                                        <label className="grid gap-1 text-sm">
                                            Label
                                            <Input
                                                value={field.label}
                                                maxLength={120}
                                                disabled={busy}
                                                aria-invalid={Boolean(
                                                    labelError,
                                                )}
                                                onChange={(event) =>
                                                    changeField(index, {
                                                        label: event.target
                                                            .value,
                                                    })
                                                }
                                            />
                                            {labelError && (
                                                <FieldError role="alert">
                                                    {labelError}
                                                </FieldError>
                                            )}
                                        </label>
                                        <label className="grid gap-1 text-sm">
                                            Response type
                                            <select
                                                className="h-11 rounded-md border bg-background px-3"
                                                value={field.type}
                                                disabled={busy}
                                                aria-invalid={Boolean(
                                                    typeError,
                                                )}
                                                onChange={(event) => {
                                                    const type = event.target
                                                        .value as LinkPageFormField['type'];
                                                    changeField(index, {
                                                        type,
                                                        optionsText:
                                                            type === 'select' &&
                                                            !field.optionsText
                                                                ? 'Option 1\nOption 2'
                                                                : field.optionsText,
                                                    });
                                                }}
                                            >
                                                <option value="text">
                                                    Short text
                                                </option>
                                                <option value="email">
                                                    Email
                                                </option>
                                                <option value="textarea">
                                                    Long text
                                                </option>
                                                <option value="select">
                                                    Choose from a list
                                                </option>
                                            </select>
                                            {typeError && (
                                                <FieldError role="alert">
                                                    {typeError}
                                                </FieldError>
                                            )}
                                        </label>
                                        {field.type === 'select' && (
                                            <label className="grid gap-1 text-sm sm:col-span-2">
                                                Options
                                                <Textarea
                                                    value={field.optionsText}
                                                    maxLength={2419}
                                                    rows={3}
                                                    disabled={busy}
                                                    aria-invalid={Boolean(
                                                        optionsError,
                                                    )}
                                                    onChange={(event) =>
                                                        changeField(index, {
                                                            optionsText:
                                                                event.target
                                                                    .value,
                                                        })
                                                    }
                                                />
                                                <span className="text-xs text-muted-foreground">
                                                    One option per line; up to
                                                    20 options.
                                                </span>
                                                {optionsError && (
                                                    <FieldError role="alert">
                                                        {optionsError}
                                                    </FieldError>
                                                )}
                                            </label>
                                        )}
                                        <label className="flex min-h-11 items-center gap-2 text-sm">
                                            <Checkbox
                                                checked={field.required}
                                                disabled={busy}
                                                aria-invalid={Boolean(
                                                    requiredError,
                                                )}
                                                onCheckedChange={(checked) =>
                                                    changeField(index, {
                                                        required:
                                                            checked === true,
                                                    })
                                                }
                                            />
                                            Required
                                            {requiredError && (
                                                <FieldError role="alert">
                                                    {requiredError}
                                                </FieldError>
                                            )}
                                        </label>
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="outline"
                                            className="w-fit"
                                            disabled={
                                                busy ||
                                                value.fields.length === 1
                                            }
                                            onClick={() => removeField(index)}
                                        >
                                            <Trash2 data-icon="inline-start" />
                                            Remove field
                                        </Button>
                                    </fieldset>
                                );
                            })}
                            {errors.fields && (
                                <FieldError role="alert">
                                    {errors.fields}
                                </FieldError>
                            )}
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                disabled={busy || value.fields.length >= 20}
                                onClick={addField}
                            >
                                <Plus data-icon="inline-start" />
                                Add field
                            </Button>
                        </div>
                    )}

                    <div className="grid gap-3 sm:grid-cols-2">
                        <label className="grid gap-1 text-sm">
                            <span className="flex min-h-11 items-center gap-2">
                                <Checkbox
                                    checked={value.is_active}
                                    aria-invalid={Boolean(errors.is_active)}
                                    disabled={busy}
                                    onCheckedChange={(checked) =>
                                        set('is_active', checked === true)
                                    }
                                />
                                Show publicly
                            </span>
                            {errors.is_active && (
                                <FieldError role="alert">
                                    {errors.is_active}
                                </FieldError>
                            )}
                        </label>
                        <label className="grid gap-1 text-sm">
                            Starts at
                            <Input
                                type="datetime-local"
                                aria-invalid={Boolean(errors.starts_at)}
                                value={localDate(value.starts_at || null)}
                                disabled={busy}
                                onChange={(event) =>
                                    set(
                                        'starts_at',
                                        utcDate(event.target.value),
                                    )
                                }
                            />
                            {errors.starts_at && (
                                <FieldError role="alert">
                                    {errors.starts_at}
                                </FieldError>
                            )}
                        </label>
                        <label className="grid gap-1 text-sm">
                            Ends at
                            <Input
                                type="datetime-local"
                                aria-invalid={Boolean(errors.ends_at)}
                                value={localDate(value.ends_at || null)}
                                disabled={busy}
                                onChange={(event) =>
                                    set('ends_at', utcDate(event.target.value))
                                }
                            />
                            {errors.ends_at && (
                                <FieldError role="alert">
                                    {errors.ends_at}
                                </FieldError>
                            )}
                        </label>
                    </div>
                </CardContent>
                <CardFooter className="flex flex-wrap justify-between gap-2">
                    {block && (
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            onClick={remove}
                            disabled={busy}
                        >
                            <Trash2 data-icon="inline-start" />
                            Remove block
                        </Button>
                    )}
                    <Button type="submit" size="sm" disabled={busy}>
                        {busy ? (
                            <Spinner data-icon="inline-start" />
                        ) : block ? (
                            <Save data-icon="inline-start" />
                        ) : (
                            <Plus data-icon="inline-start" />
                        )}
                        {block ? 'Save block' : 'Add block'}
                    </Button>
                </CardFooter>
            </form>
        </Card>
    );
}
