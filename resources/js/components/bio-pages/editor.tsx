import { router, useForm, usePage } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowUp,
    AtSign,
    Code2,
    Eye,
    EyeOff,
    Image as ImageIcon,
    Link2,
    MoreHorizontal,
    Palette,
    Pencil,
    Pin,
    Plus,
    Trash2,
    Type as TypeIcon,
} from 'lucide-react';
import { type FormEvent, useEffect, useId, useState } from 'react';
import { AppearanceEditor } from '@/components/bio-pages/appearance-editor';
import { BioPageInsightsPanel } from '@/components/bio-pages/insights-panel';
import { LivePreview } from '@/components/bio-pages/live-preview';
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
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Field, FieldError, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import {
    insights as bioPageInsightsRoute,
    update as updateBioPage,
} from '@/routes/bio-pages';
import {
    destroy as destroyBioLink,
    store as storeBioLink,
    update as updateBioLink,
} from '@/routes/bio-pages/links';
import {
    destroy as destroyBioBlock,
    store as storeBioBlock,
    update as updateBioBlock,
} from '@/routes/bio-pages/blocks';
import { reorder as reorderBioContent } from '@/routes/bio-pages/content';
import { cn } from '@/lib/utils';
import type {
    BioContentBlockPayload,
    BioLinkPayload,
    BioPage,
    BioPageEditorProps,
    BioPageItem,
} from '@/types/bio-pages';
import { toBioPage } from '@/types/bio-pages';

type PageForm = {
    handle: string;
    display_name: string;
    bio: string;
    is_published: boolean;
    appearance: BioPage['appearance'];
};
type BlockForm = {
    type: 'text' | 'image' | 'social' | 'embed';
    text: string;
    alt_text: string;
    provider: string;
    identifier: string;
    provider_type: string;
    is_active: boolean;
    is_pinned: boolean;
    starts_at: string;
    ends_at: string;
    image: File | null;
};
const initialBlock = (): BlockForm => ({
    type: 'text',
    text: '',
    alt_text: '',
    provider: 'instagram',
    identifier: '',
    provider_type: 'track',
    is_active: true,
    is_pinned: false,
    starts_at: '',
    ends_at: '',
    image: null,
});

function ItemTypeIcon({ type }: { type: BioPageItem['type'] }) {
    switch (type) {
        case 'link':
            return <Link2 aria-hidden="true" className="size-4" />;
        case 'text':
            return <TypeIcon aria-hidden="true" className="size-4" />;
        case 'image':
            return <ImageIcon aria-hidden="true" className="size-4" />;
        case 'social':
            return <AtSign aria-hidden="true" className="size-4" />;
        case 'embed':
            return <Code2 aria-hidden="true" className="size-4" />;
    }
}

function compactUrl(value: string) {
    return value.replace(/^https?:\/\//, '').replace(/\/$/, '');
}

function itemSummary(item: BioPageItem) {
    switch (item.type) {
        case 'link':
            return { title: item.title, meta: compactUrl(item.url) };
        case 'text':
            return {
                title: item.text.trim() || 'Text block',
                meta: 'Text block',
            };
        case 'image':
            return {
                title: item.altText.trim() || 'Image block',
                meta: 'Image block',
            };
        case 'social':
            return {
                title: item.identifier || 'Social block',
                meta: `Social block · ${item.provider}`,
            };
        case 'embed':
            return {
                title: item.identifier || 'Embed block',
                meta: `Embed block · ${item.provider}`,
            };
    }
}

function localDate(value: string | null) {
    if (!value) return '';
    const date = new Date(value);
    return Number.isNaN(date.valueOf())
        ? ''
        : new Date(date.valueOf() - date.getTimezoneOffset() * 60000)
              .toISOString()
              .slice(0, 16);
}
function utcDate(value: string) {
    return value ? new Date(value).toISOString() : '';
}
function scheduleFields(
    value: BlockForm,
    set: (key: keyof BlockForm, next: string | boolean | File | null) => void,
    errors: Record<string, string>,
) {
    return (
        <div className="grid gap-3 sm:grid-cols-2">
            <div className="grid gap-1">
                <label className="flex min-h-11 items-center gap-2 text-sm">
                    <Checkbox
                        aria-invalid={Boolean(errors.is_active)}
                        checked={value.is_active}
                        onCheckedChange={(checked) =>
                            set('is_active', checked === true)
                        }
                    />
                    Show publicly
                </label>
                {errors.is_active && (
                    <FieldError role="alert">{errors.is_active}</FieldError>
                )}
            </div>
            <div className="grid gap-1">
                <label className="flex min-h-11 items-center gap-2 text-sm">
                    <Checkbox
                        aria-invalid={Boolean(errors.is_pinned)}
                        checked={value.is_pinned}
                        onCheckedChange={(checked) =>
                            set('is_pinned', checked === true)
                        }
                    />
                    Pin near the top
                </label>
                {errors.is_pinned && (
                    <FieldError role="alert">{errors.is_pinned}</FieldError>
                )}
            </div>
            <label className="grid gap-1 text-sm">
                Starts at
                <Input
                    aria-invalid={Boolean(errors.starts_at)}
                    className="h-10"
                    type="datetime-local"
                    value={localDate(value.starts_at || null)}
                    onChange={(event) =>
                        set('starts_at', utcDate(event.target.value))
                    }
                />
                {errors.starts_at && (
                    <FieldError role="alert">{errors.starts_at}</FieldError>
                )}
            </label>
            <label className="grid gap-1 text-sm">
                Ends at
                <Input
                    aria-invalid={Boolean(errors.ends_at)}
                    className="h-10"
                    type="datetime-local"
                    value={localDate(value.ends_at || null)}
                    onChange={(event) =>
                        set('ends_at', utcDate(event.target.value))
                    }
                />
                {errors.ends_at && (
                    <FieldError role="alert">{errors.ends_at}</FieldError>
                )}
            </label>
        </div>
    );
}
function BlockFormEditor({
    page,
    block,
    onClose,
}: {
    page: BioPage;
    block?: BioContentBlockPayload;
    onClose: () => void;
}) {
    const fieldPrefix = useId();
    const platform = usePage().props.platform;
    const allowedBlocks: string[] = platform?.availableBlocks ?? [
        'link',
        'text',
        'image',
        'social',
        'embed',
    ];
    const blockOptions = (['text', 'image', 'social', 'embed'] as const).filter(
        (option) => allowedBlocks.includes(option) || block?.type === option,
    );
    const initialType: BlockForm['type'] =
        block?.type ?? blockOptions[0] ?? 'text';
    const [value, setValue] = useState<BlockForm>(() => ({
        ...initialBlock(),
        type: initialType,
        ...(block
            ? {
                  type: block.type,
                  text: block.type === 'text' ? block.text : '',
                  alt_text: block.type === 'image' ? block.altText : '',
                  provider:
                      block.type === 'social' || block.type === 'embed'
                          ? block.provider
                          : 'instagram',
                  identifier:
                      block.type === 'social' || block.type === 'embed'
                          ? block.identifier
                          : '',
                  provider_type:
                      block.type === 'embed'
                          ? (block.providerType ?? 'track')
                          : 'track',
                  is_active: block.isActive,
                  is_pinned: block.isPinned,
                  starts_at: block.startsAt ?? '',
                  ends_at: block.endsAt ?? '',
              }
            : {}),
    }));
    const [busy, setBusy] = useState(false);
    const [validationErrors, setValidationErrors] = useState<
        Record<string, string>
    >({});
    const set = (
        key: keyof BlockForm,
        next: string | boolean | File | null,
    ) => {
        setValue((current) => ({ ...current, [key]: next }));
        setValidationErrors((current) => {
            if (!(key in current)) return current;
            const nextErrors = { ...current };
            delete nextErrors[key];
            return nextErrors;
        });
    };
    function submit(event: FormEvent) {
        event.preventDefault();
        setBusy(true);
        const data = new FormData();
        data.append('type', value.type);
        if (value.type === 'text') {
            data.append('text', value.text);
        } else if (value.type === 'image') {
            data.append('alt_text', value.alt_text);
            if (value.image) data.append('image', value.image);
        } else {
            data.append('provider', value.provider);
            data.append('identifier', value.identifier);
            if (value.type === 'embed' && value.provider === 'spotify') {
                data.append('provider_type', value.provider_type);
            }
        }
        for (const field of [
            'is_active',
            'is_pinned',
            'starts_at',
            'ends_at',
        ] as const) {
            const fieldValue = value[field];
            data.append(
                field,
                typeof fieldValue === 'boolean'
                    ? fieldValue
                        ? '1'
                        : '0'
                    : String(fieldValue ?? ''),
            );
        }
        const options = {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: onClose,
            onError: setValidationErrors,
            onFinish: () => setBusy(false),
        };
        if (block) {
            data.append('_method', 'patch');
            router.post(
                updateBioBlock({ bioPage: page.id, bioBlock: block.id }).url,
                data,
                options,
            );
        } else router.post(storeBioBlock(page.id).url, data, options);
    }
    return (
        <form
            onSubmit={submit}
            className="grid gap-3 rounded-xl border bg-surface-secondary/40 p-4"
        >
            <Field data-invalid={Boolean(validationErrors.type)}>
                <FieldLabel htmlFor={`${fieldPrefix}-content-type`}>
                    Content type
                </FieldLabel>
                <Select
                    value={value.type}
                    onValueChange={(selectedType) => {
                        const type = selectedType as BlockForm['type'];
                        setValidationErrors({});
                        setValue((current) => ({
                            ...current,
                            type,
                            provider:
                                type === 'embed' ? 'youtube' : 'instagram',
                            provider_type: 'track',
                        }));
                    }}
                >
                    <SelectTrigger
                        id={`${fieldPrefix}-content-type`}
                        aria-invalid={Boolean(validationErrors.type)}
                        className="h-11 w-full"
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        {blockOptions.map((option) => (
                            <SelectItem key={option} value={option}>
                                {option}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                {validationErrors.type && (
                    <FieldError role="alert">
                        {validationErrors.type}
                    </FieldError>
                )}
            </Field>
            {value.type === 'text' && (
                <Field
                    className="gap-1"
                    data-invalid={Boolean(validationErrors.text)}
                >
                    <FieldLabel htmlFor={`${fieldPrefix}-text`}>
                        Text
                    </FieldLabel>
                    <Textarea
                        id={`${fieldPrefix}-text`}
                        aria-invalid={Boolean(validationErrors.text)}
                        className="min-h-24 p-3"
                        maxLength={5000}
                        value={value.text}
                        onChange={(event) => set('text', event.target.value)}
                    />
                    {validationErrors.text && (
                        <FieldError role="alert">
                            {validationErrors.text}
                        </FieldError>
                    )}
                </Field>
            )}
            {value.type === 'image' && (
                <>
                    <Field data-invalid={Boolean(validationErrors.image)}>
                        <FieldLabel htmlFor={`${fieldPrefix}-image`}>
                            Choose image
                        </FieldLabel>
                        <Input
                            id={`${fieldPrefix}-image`}
                            aria-invalid={Boolean(validationErrors.image)}
                            type="file"
                            accept="image/*"
                            onChange={(event) =>
                                set('image', event.target.files?.[0] ?? null)
                            }
                        />
                        {validationErrors.image && (
                            <FieldError role="alert">
                                {validationErrors.image}
                            </FieldError>
                        )}
                    </Field>
                    <label className="grid gap-1 text-sm">
                        Alt text
                        <Input
                            aria-invalid={Boolean(validationErrors.alt_text)}
                            maxLength={250}
                            value={value.alt_text}
                            onChange={(event) =>
                                set('alt_text', event.target.value)
                            }
                        />
                        {validationErrors.alt_text && (
                            <FieldError role="alert">
                                {validationErrors.alt_text}
                            </FieldError>
                        )}
                    </label>
                </>
            )}
            {(value.type === 'social' || value.type === 'embed') && (
                <>
                    <Field
                        className="gap-1"
                        data-invalid={Boolean(validationErrors.provider)}
                    >
                        <FieldLabel htmlFor={`${fieldPrefix}-provider`}>
                            Provider
                        </FieldLabel>
                        <Select
                            value={value.provider}
                            onValueChange={(provider) =>
                                set('provider', provider)
                            }
                        >
                            <SelectTrigger
                                id={`${fieldPrefix}-provider`}
                                aria-invalid={Boolean(
                                    validationErrors.provider,
                                )}
                                className="h-11 w-full"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {(value.type === 'social'
                                    ? [
                                          'instagram',
                                          'x',
                                          'tiktok',
                                          'youtube',
                                          'linkedin',
                                          'github',
                                      ]
                                    : [
                                          'youtube',
                                          'soundcloud',
                                          'vimeo',
                                          'spotify',
                                          'twitch',
                                          'tiktok',
                                      ]
                                ).map((provider) => (
                                    <SelectItem key={provider} value={provider}>
                                        {provider}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {validationErrors.provider && (
                            <FieldError role="alert">
                                {validationErrors.provider}
                            </FieldError>
                        )}
                        {validationErrors.provider_type &&
                            !(
                                value.type === 'embed' &&
                                value.provider === 'spotify'
                            ) && (
                                <FieldError role="alert">
                                    {validationErrors.provider_type}
                                </FieldError>
                            )}
                    </Field>
                    <label className="grid gap-1 text-sm">
                        {value.type === 'social' ? 'Handle' : 'Content ID'}
                        <Input
                            aria-invalid={Boolean(validationErrors.identifier)}
                            required
                            value={value.identifier}
                            onChange={(event) =>
                                set('identifier', event.target.value)
                            }
                        />
                        {validationErrors.identifier && (
                            <FieldError role="alert">
                                {validationErrors.identifier}
                            </FieldError>
                        )}
                    </label>
                    {value.type === 'embed' && value.provider === 'spotify' && (
                        <Field
                            className="gap-1"
                            data-invalid={Boolean(
                                validationErrors.provider_type,
                            )}
                        >
                            <FieldLabel
                                htmlFor={`${fieldPrefix}-provider-type`}
                            >
                                Spotify content type
                            </FieldLabel>
                            <Select
                                value={value.provider_type}
                                onValueChange={(providerType) =>
                                    set('provider_type', providerType)
                                }
                            >
                                <SelectTrigger
                                    id={`${fieldPrefix}-provider-type`}
                                    aria-invalid={Boolean(
                                        validationErrors.provider_type,
                                    )}
                                    className="h-11 w-full"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {[
                                        'track',
                                        'album',
                                        'playlist',
                                        'episode',
                                        'show',
                                    ].map((type) => (
                                        <SelectItem key={type} value={type}>
                                            {type}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {validationErrors.provider_type && (
                                <FieldError role="alert">
                                    {validationErrors.provider_type}
                                </FieldError>
                            )}
                        </Field>
                    )}
                </>
            )}
            {scheduleFields(value, set, validationErrors)}
            <div className="flex gap-2">
                <Button type="submit" disabled={busy}>
                    {busy ? 'Saving…' : block ? 'Save block' : 'Add block'}
                </Button>
                <Button type="button" variant="outline" onClick={onClose}>
                    Cancel
                </Button>
            </div>
        </form>
    );
}
function LinkForm({
    page,
    link,
    onClose,
}: {
    page: BioPage;
    link?: BioLinkPayload;
    onClose: () => void;
}) {
    const [values, setValues] = useState({
        title: link?.title ?? '',
        destination_url: link?.destination_url ?? '',
        is_active: link?.is_active ?? true,
        is_pinned: link?.is_pinned ?? false,
        starts_at: link?.starts_at ?? '',
        ends_at: link?.ends_at ?? '',
    });
    const [busy, setBusy] = useState(false);
    const [validationErrors, setValidationErrors] = useState<
        Record<string, string>
    >({});
    const clearValidationError = (key: string) => {
        setValidationErrors((current) => {
            if (!(key in current)) return current;
            const next = { ...current };
            delete next[key];
            return next;
        });
    };
    function submit(event: FormEvent) {
        event.preventDefault();
        setBusy(true);
        const data = {
            ...values,
            starts_at: values.starts_at || null,
            ends_at: values.ends_at || null,
        };
        const options = {
            preserveScroll: true,
            onError: setValidationErrors,
            onFinish: () => setBusy(false),
            onSuccess: onClose,
        };
        if (link)
            router.patch(
                updateBioLink({ bioPage: page.id, bioLink: link.id }).url,
                data,
                options,
            );
        else router.post(storeBioLink(page.id).url, data, options);
    }
    return (
        <form
            onSubmit={submit}
            className="grid gap-3 rounded-xl border bg-surface-secondary/40 p-4"
        >
            <label className="grid gap-1 text-sm">
                Button label
                <Input
                    aria-invalid={Boolean(validationErrors.title)}
                    required
                    value={values.title}
                    onChange={(event) => {
                        setValues((current) => ({
                            ...current,
                            title: event.target.value,
                        }));
                        clearValidationError('title');
                    }}
                />
                {validationErrors.title && (
                    <FieldError role="alert">
                        {validationErrors.title}
                    </FieldError>
                )}
            </label>
            <label className="grid gap-1 text-sm">
                Destination URL
                <Input
                    aria-invalid={Boolean(validationErrors.destination_url)}
                    required
                    type="url"
                    value={values.destination_url}
                    onChange={(event) => {
                        setValues((current) => ({
                            ...current,
                            destination_url: event.target.value,
                        }));
                        clearValidationError('destination_url');
                    }}
                />
                {validationErrors.destination_url && (
                    <FieldError role="alert">
                        {validationErrors.destination_url}
                    </FieldError>
                )}
            </label>
            {scheduleFields(
                { ...initialBlock(), ...values },
                (key, next) => {
                    setValues((old) => ({ ...old, [key]: next }));
                    clearValidationError(key);
                },
                validationErrors,
            )}
            <div className="flex gap-2">
                <Button type="submit" disabled={busy}>
                    {link ? 'Save button' : 'Add button'}
                </Button>
                <Button type="button" variant="outline" onClick={onClose}>
                    Cancel
                </Button>
            </div>
        </form>
    );
}
export function BioPageEditor({
    bioPage: payload,
    bioLinks,
    contentBlocks = [],
    previewAt,
    insights,
}: BioPageEditorProps) {
    const page = toBioPage(payload);
    const platform = usePage().props.platform;
    const linkAllowed = (
        platform?.availableBlocks ?? [
            'link',
            'text',
            'image',
            'social',
            'embed',
        ]
    ).includes('link');
    const [tab, setTab] = useState<'content' | 'appearance' | 'insights'>(
        insights ? 'insights' : 'content',
    );
    const [adding, setAdding] = useState<'block' | 'link' | null>(null);
    const [editing, setEditing] = useState<number | null>(null);
    const form = useForm<PageForm>({
        handle: page.handle,
        display_name: page.displayName,
        bio: page.bio ?? '',
        is_published: page.isPublished,
        appearance: page.appearance,
    });
    useEffect(() => {
        form.setData('is_published', page.isPublished);
        form.setDefaults('is_published', page.isPublished);
    }, [form.setData, form.setDefaults, page.isPublished]);
    const items: BioPageItem[] = [
        ...bioLinks.map((link) => ({
            type: 'link' as const,
            id: link.id,
            title: link.title,
            url: link.destination_url,
            position: link.position,
            isActive: link.is_active,
            isPinned: link.is_pinned ?? false,
            startsAt: link.starts_at ?? null,
            endsAt: link.ends_at ?? null,
        })),
        ...contentBlocks,
    ].sort(
        (a, b) =>
            Number(b.isPinned) - Number(a.isPinned) || a.position - b.position,
    );
    function save(event: FormEvent) {
        event.preventDefault();
        form.patch(updateBioPage(page.id).url, { preserveScroll: true });
    }
    function canMove(from: number, to: number) {
        return (
            to >= 0 &&
            to < items.length &&
            items[from]?.isPinned === items[to]?.isPinned
        );
    }
    function move(from: number, to: number) {
        if (!canMove(from, to)) return;
        const reordered = [...items];
        const [item] = reordered.splice(from, 1);
        reordered.splice(to, 0, item);
        router.patch(
            reorderBioContent(page.id).url,
            {
                items: reordered.map(({ type, id }) => ({
                    type: type === 'link' ? 'link' : 'block',
                    id,
                })),
            },
            { preserveScroll: true },
        );
    }
    const presentation = {
        handle: form.data.handle,
        displayName: form.data.display_name,
        bio: form.data.bio || null,
        appearance: form.data.appearance,
        items,
    };
    return (
        <div
            className={cn(
                'grid min-w-0 gap-6 xl:items-start',
                tab === 'insights'
                    ? 'xl:grid-cols-1'
                    : 'xl:grid-cols-[minmax(0,1fr)_minmax(20rem,28rem)]',
            )}
        >
            <div className="min-w-0">
                <div
                    className="mb-5 flex gap-1 border-b"
                    role="tablist"
                    aria-label="Bio page editor"
                >
                    {(['content', 'appearance', 'insights'] as const).map(
                        (choice) => (
                            <button
                                key={choice}
                                type="button"
                                role="tab"
                                aria-selected={tab === choice}
                                className={cn(
                                    'min-h-11 border-b-2 px-4 text-sm font-medium capitalize transition-colors focus-visible:outline-2 focus-visible:outline-ring',
                                    tab === choice
                                        ? 'border-primary text-foreground'
                                        : 'border-transparent text-muted-foreground hover:text-foreground',
                                )}
                                onClick={() => {
                                    setTab(choice);
                                    if (choice === 'insights' && !insights) {
                                        router.get(
                                            bioPageInsightsRoute(page.id).url,
                                            { days: 30 },
                                            {
                                                preserveState: true,
                                                preserveScroll: true,
                                            },
                                        );
                                    }
                                }}
                            >
                                {choice}
                            </button>
                        ),
                    )}
                </div>
                {tab !== 'insights' && (
                    <form onSubmit={save}>
                        <Card className="overflow-hidden">
                            <CardHeader>
                                <CardTitle
                                    className={
                                        tab === 'appearance'
                                            ? 'flex items-center gap-2'
                                            : undefined
                                    }
                                >
                                    {tab === 'appearance' && (
                                        <Palette
                                            className="size-4 text-muted-foreground"
                                            aria-hidden="true"
                                        />
                                    )}
                                    {tab === 'content'
                                        ? 'Your page'
                                        : 'Appearance'}
                                </CardTitle>
                                <CardDescription>
                                    {tab === 'content'
                                        ? 'Set your profile details and publication state.'
                                        : 'Style the page, typography, accent, and background. The preview updates as you choose.'}
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-4">
                                {tab === 'content' ? (
                                    <>
                                        <label className="grid gap-1 text-sm">
                                            Handle
                                            <Input
                                                aria-invalid={Boolean(
                                                    form.errors.handle,
                                                )}
                                                value={form.data.handle}
                                                onChange={(event) =>
                                                    form.setData(
                                                        'handle',
                                                        event.target.value.toLowerCase(),
                                                    )
                                                }
                                            />
                                            {form.errors.handle && (
                                                <span
                                                    role="alert"
                                                    className="text-sm text-destructive"
                                                >
                                                    {form.errors.handle}
                                                </span>
                                            )}
                                        </label>
                                        <label className="grid gap-1 text-sm">
                                            Display name
                                            <Input
                                                aria-invalid={Boolean(
                                                    form.errors.display_name,
                                                )}
                                                value={form.data.display_name}
                                                onChange={(event) =>
                                                    form.setData(
                                                        'display_name',
                                                        event.target.value,
                                                    )
                                                }
                                            />
                                            {form.errors.display_name && (
                                                <span
                                                    role="alert"
                                                    className="text-sm text-destructive"
                                                >
                                                    {form.errors.display_name}
                                                </span>
                                            )}
                                        </label>
                                        <label className="grid gap-1 text-sm">
                                            Bio
                                            <Textarea
                                                aria-invalid={Boolean(
                                                    form.errors.bio,
                                                )}
                                                maxLength={280}
                                                value={form.data.bio}
                                                onChange={(event) =>
                                                    form.setData(
                                                        'bio',
                                                        event.target.value,
                                                    )
                                                }
                                            />
                                            {form.errors.bio && (
                                                <span
                                                    role="alert"
                                                    className="text-sm text-destructive"
                                                >
                                                    {form.errors.bio}
                                                </span>
                                            )}
                                        </label>
                                        <label className="flex min-h-11 items-center gap-2 text-sm">
                                            <Checkbox
                                                checked={form.data.is_published}
                                                onCheckedChange={(checked) =>
                                                    form.setData(
                                                        'is_published',
                                                        checked === true,
                                                    )
                                                }
                                            />
                                            Publish this page
                                        </label>
                                    </>
                                ) : (
                                    <AppearanceEditor
                                        value={form.data.appearance}
                                        onChange={(appearance) =>
                                            form.setData(
                                                'appearance',
                                                appearance,
                                            )
                                        }
                                    />
                                )}
                            </CardContent>
                            <CardFooter className="flex flex-wrap items-center gap-3">
                                <Button
                                    type="submit"
                                    disabled={form.processing}
                                >
                                    {tab === 'appearance'
                                        ? 'Save appearance'
                                        : 'Save changes'}
                                </Button>
                                {form.isDirty && (
                                    <span className="text-xs text-muted-foreground">
                                        Unsaved changes
                                    </span>
                                )}
                            </CardFooter>
                        </Card>
                    </form>
                )}
                {tab === 'insights' && insights && (
                    <BioPageInsightsPanel
                        insights={insights}
                        onWindowChange={(days) =>
                            router.get(
                                bioPageInsightsRoute(page.id).url,
                                { days },
                                {
                                    preserveState: true,
                                    preserveScroll: true,
                                },
                            )
                        }
                    />
                )}
                {tab === 'insights' && !insights && (
                    <p role="status" className="text-sm text-muted-foreground">
                        Loading insights…
                    </p>
                )}
                {tab === 'content' && (
                    <section className="mt-6" aria-labelledby="content-title">
                        <Card className="overflow-hidden">
                            <CardHeader className="gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div className="min-w-0">
                                    <CardTitle as="h2" id="content-title">
                                        Page content
                                    </CardTitle>
                                    <CardDescription className="mt-1">
                                        Buttons and blocks appear in this order.
                                        Use the arrow buttons or Alt + arrow
                                        keys to reorder.
                                    </CardDescription>
                                </div>
                                <div className="flex shrink-0 flex-wrap gap-2">
                                    {linkAllowed ? (
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="outline"
                                            aria-expanded={adding === 'link'}
                                            aria-controls="page-content-add-form"
                                            onClick={() =>
                                                setAdding(
                                                    adding === 'link'
                                                        ? null
                                                        : 'link',
                                                )
                                            }
                                        >
                                            <Link2 data-icon="inline-start" />
                                            Add button
                                        </Button>
                                    ) : null}
                                    <Button
                                        type="button"
                                        size="sm"
                                        aria-expanded={adding === 'block'}
                                        aria-controls="page-content-add-form"
                                        onClick={() =>
                                            setAdding(
                                                adding === 'block'
                                                    ? null
                                                    : 'block',
                                            )
                                        }
                                    >
                                        <Plus data-icon="inline-start" />
                                        Add content
                                    </Button>
                                </div>
                            </CardHeader>
                            <CardContent className="px-0">
                                {(adding === 'block' || adding === 'link') && (
                                    <div
                                        id="page-content-add-form"
                                        className="border-t px-4 py-4"
                                    >
                                        {adding === 'block' && (
                                            <BlockFormEditor
                                                page={page}
                                                onClose={() => setAdding(null)}
                                            />
                                        )}
                                        {adding === 'link' && (
                                            <LinkForm
                                                page={page}
                                                onClose={() => setAdding(null)}
                                            />
                                        )}
                                    </div>
                                )}
                                {items.length === 0 ? (
                                    <p className="border-t px-4 py-8 text-sm text-muted-foreground">
                                        Add a button or content block to start
                                        your page.
                                    </p>
                                ) : (
                                    <ol className="divide-y border-t">
                                        {items.map((item, index) => {
                                            const block =
                                                item.type === 'link'
                                                    ? undefined
                                                    : contentBlocks.find(
                                                          (candidate) =>
                                                              candidate.id ===
                                                              item.id,
                                                      );
                                            const link =
                                                item.type === 'link'
                                                    ? bioLinks.find(
                                                          (candidate) =>
                                                              candidate.id ===
                                                              item.id,
                                                      )
                                                    : undefined;
                                            const editKey = link
                                                ? -link.id
                                                : block?.id;
                                            const summary = itemSummary(item);
                                            const toggleEditing = () =>
                                                setEditing(
                                                    editing === editKey
                                                        ? null
                                                        : (editKey ?? null),
                                                );
                                            const toggleVisibility = () => {
                                                if (!link) return;
                                                router.patch(
                                                    updateBioLink({
                                                        bioPage: page.id,
                                                        bioLink: link.id,
                                                    }).url,
                                                    {
                                                        is_active:
                                                            !link.is_active,
                                                    },
                                                    { preserveScroll: true },
                                                );
                                            };
                                            const deleteItem = () => {
                                                const message = link
                                                    ? 'Delete this button?'
                                                    : 'Delete this content block?';
                                                if (!window.confirm(message)) {
                                                    return;
                                                }
                                                if (link) {
                                                    router.delete(
                                                        destroyBioLink({
                                                            bioPage: page.id,
                                                            bioLink: link.id,
                                                        }).url,
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    );
                                                } else if (block) {
                                                    router.delete(
                                                        destroyBioBlock({
                                                            bioPage: page.id,
                                                            bioBlock: block.id,
                                                        }).url,
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    );
                                                }
                                            };

                                            return (
                                                <li
                                                    key={`${item.type}-${item.id}`}
                                                    onKeyDown={(event) => {
                                                        if (
                                                            event.altKey &&
                                                            event.key ===
                                                                'ArrowUp' &&
                                                            canMove(
                                                                index,
                                                                index - 1,
                                                            )
                                                        ) {
                                                            event.preventDefault();
                                                            move(
                                                                index,
                                                                index - 1,
                                                            );
                                                        }
                                                        if (
                                                            event.altKey &&
                                                            event.key ===
                                                                'ArrowDown' &&
                                                            canMove(
                                                                index,
                                                                index + 1,
                                                            )
                                                        ) {
                                                            event.preventDefault();
                                                            move(
                                                                index,
                                                                index + 1,
                                                            );
                                                        }
                                                    }}
                                                >
                                                    <div className="flex flex-wrap items-center gap-3 px-4 py-3 transition-colors hover:bg-surface-secondary/50">
                                                        <span className="flex size-9 shrink-0 items-center justify-center rounded-xl bg-surface-secondary text-muted-foreground">
                                                            <ItemTypeIcon
                                                                type={item.type}
                                                            />
                                                        </span>
                                                        <div className="min-w-0 flex-1">
                                                            <div className="flex min-w-0 items-center gap-1.5">
                                                                <p className="truncate text-sm font-medium">
                                                                    {
                                                                        summary.title
                                                                    }
                                                                </p>
                                                                {item.isPinned && (
                                                                    <span
                                                                        title="Pinned"
                                                                        className="shrink-0 text-muted-foreground"
                                                                    >
                                                                        <Pin
                                                                            aria-hidden="true"
                                                                            className="size-3.5"
                                                                        />
                                                                        <span className="sr-only">
                                                                            Pinned
                                                                        </span>
                                                                    </span>
                                                                )}
                                                                {!item.isActive && (
                                                                    <span className="shrink-0 text-xs text-muted-foreground">
                                                                        Hidden
                                                                    </span>
                                                                )}
                                                            </div>
                                                            <p
                                                                className="truncate text-xs text-muted-foreground"
                                                                title={
                                                                    item.type ===
                                                                    'link'
                                                                        ? item.url
                                                                        : summary.meta
                                                                }
                                                            >
                                                                {summary.meta}
                                                            </p>
                                                        </div>
                                                        <div className="ml-auto flex shrink-0 items-center gap-0.5">
                                                            <Tooltip>
                                                                <TooltipTrigger
                                                                    asChild
                                                                >
                                                                    <Button
                                                                        type="button"
                                                                        variant="ghost"
                                                                        size="icon"
                                                                        className="size-8 rounded-full text-muted-foreground"
                                                                        aria-label={`Move ${summary.title} up`}
                                                                        disabled={
                                                                            !canMove(
                                                                                index,
                                                                                index -
                                                                                    1,
                                                                            )
                                                                        }
                                                                        onClick={() =>
                                                                            move(
                                                                                index,
                                                                                index -
                                                                                    1,
                                                                            )
                                                                        }
                                                                    >
                                                                        <ArrowUp />
                                                                    </Button>
                                                                </TooltipTrigger>
                                                                <TooltipContent>
                                                                    Move up
                                                                </TooltipContent>
                                                            </Tooltip>
                                                            <Tooltip>
                                                                <TooltipTrigger
                                                                    asChild
                                                                >
                                                                    <Button
                                                                        type="button"
                                                                        variant="ghost"
                                                                        size="icon"
                                                                        className="size-8 rounded-full text-muted-foreground"
                                                                        aria-label={`Move ${summary.title} down`}
                                                                        disabled={
                                                                            !canMove(
                                                                                index,
                                                                                index +
                                                                                    1,
                                                                            )
                                                                        }
                                                                        onClick={() =>
                                                                            move(
                                                                                index,
                                                                                index +
                                                                                    1,
                                                                            )
                                                                        }
                                                                    >
                                                                        <ArrowDown />
                                                                    </Button>
                                                                </TooltipTrigger>
                                                                <TooltipContent>
                                                                    Move down
                                                                </TooltipContent>
                                                            </Tooltip>
                                                            {(link ||
                                                                block) && (
                                                                <Tooltip>
                                                                    <TooltipTrigger
                                                                        asChild
                                                                    >
                                                                        <Button
                                                                            type="button"
                                                                            variant="ghost"
                                                                            size="icon"
                                                                            className="hidden size-8 rounded-full text-muted-foreground sm:inline-flex"
                                                                            aria-label={`Edit ${summary.title}`}
                                                                            aria-expanded={
                                                                                editing ===
                                                                                editKey
                                                                            }
                                                                            onClick={
                                                                                toggleEditing
                                                                            }
                                                                        >
                                                                            <Pencil />
                                                                        </Button>
                                                                    </TooltipTrigger>
                                                                    <TooltipContent>
                                                                        Edit
                                                                    </TooltipContent>
                                                                </Tooltip>
                                                            )}
                                                            {(link ||
                                                                block) && (
                                                                <DropdownMenu>
                                                                    <DropdownMenuTrigger
                                                                        asChild
                                                                    >
                                                                        <Button
                                                                            type="button"
                                                                            variant="ghost"
                                                                            size="icon"
                                                                            className="size-8 rounded-full text-muted-foreground"
                                                                            aria-label={`More actions for ${summary.title}`}
                                                                            title="More actions"
                                                                        >
                                                                            <MoreHorizontal />
                                                                        </Button>
                                                                    </DropdownMenuTrigger>
                                                                    <DropdownMenuContent
                                                                        align="end"
                                                                        className="w-44"
                                                                    >
                                                                        <DropdownMenuItem
                                                                            className="sm:hidden"
                                                                            onSelect={
                                                                                toggleEditing
                                                                            }
                                                                        >
                                                                            <Pencil />
                                                                            Edit
                                                                        </DropdownMenuItem>
                                                                        {link && (
                                                                            <>
                                                                                <DropdownMenuItem
                                                                                    onSelect={
                                                                                        toggleVisibility
                                                                                    }
                                                                                >
                                                                                    {link.is_active ? (
                                                                                        <EyeOff />
                                                                                    ) : (
                                                                                        <Eye />
                                                                                    )}
                                                                                    {link.is_active
                                                                                        ? 'Hide from page'
                                                                                        : 'Show on page'}
                                                                                </DropdownMenuItem>
                                                                                <DropdownMenuSeparator />
                                                                            </>
                                                                        )}
                                                                        <DropdownMenuItem
                                                                            variant="destructive"
                                                                            onSelect={
                                                                                deleteItem
                                                                            }
                                                                        >
                                                                            <Trash2 />
                                                                            Delete
                                                                        </DropdownMenuItem>
                                                                    </DropdownMenuContent>
                                                                </DropdownMenu>
                                                            )}
                                                        </div>
                                                    </div>
                                                    {link &&
                                                        editing === editKey && (
                                                            <div className="px-4 pb-4">
                                                                <LinkForm
                                                                    page={page}
                                                                    link={link}
                                                                    onClose={() =>
                                                                        setEditing(
                                                                            null,
                                                                        )
                                                                    }
                                                                />
                                                            </div>
                                                        )}
                                                    {block &&
                                                        editing === editKey && (
                                                            <div className="px-4 pb-4">
                                                                <BlockFormEditor
                                                                    page={page}
                                                                    block={
                                                                        block
                                                                    }
                                                                    onClose={() =>
                                                                        setEditing(
                                                                            null,
                                                                        )
                                                                    }
                                                                />
                                                            </div>
                                                        )}
                                                </li>
                                            );
                                        })}
                                    </ol>
                                )}
                            </CardContent>
                        </Card>
                    </section>
                )}
            </div>
            {tab !== 'insights' && (
                <aside className="xl:sticky xl:top-6">
                    <LivePreview
                        presentation={presentation}
                        isPublished={page.isPublished}
                        previewAt={previewAt}
                    />
                </aside>
            )}
        </div>
    );
}
