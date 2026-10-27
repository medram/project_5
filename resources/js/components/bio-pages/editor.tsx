import { router, useForm } from '@inertiajs/react';
import { ArrowDown, ArrowUp, Plus, Trash2 } from 'lucide-react';
import { type FormEvent, useState } from 'react';
import { LivePreview } from '@/components/bio-pages/live-preview';
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
import { Textarea } from '@/components/ui/textarea';
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
                <input
                    aria-invalid={Boolean(errors.starts_at)}
                    className="h-10 rounded-md border bg-background px-3"
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
                <input
                    aria-invalid={Boolean(errors.ends_at)}
                    className="h-10 rounded-md border bg-background px-3"
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
    const [value, setValue] = useState<BlockForm>(() => ({
        ...initialBlock(),
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
            className="grid gap-3 rounded-lg border bg-muted/20 p-4"
        >
            <label className="grid gap-1 text-sm">
                Content type
                <select
                    aria-invalid={Boolean(validationErrors.type)}
                    className="h-11 rounded-md border bg-background px-3"
                    value={value.type}
                    onChange={(event) => {
                        const type = event.target.value as BlockForm['type'];
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
                    {['text', 'image', 'social', 'embed'].map((option) => (
                        <option key={option}>{option}</option>
                    ))}
                </select>
                {validationErrors.type && (
                    <FieldError role="alert">
                        {validationErrors.type}
                    </FieldError>
                )}
            </label>
            {value.type === 'text' && (
                <label className="grid gap-1 text-sm">
                    Text
                    <textarea
                        aria-invalid={Boolean(validationErrors.text)}
                        className="min-h-24 rounded-md border bg-background p-3"
                        maxLength={5000}
                        value={value.text}
                        onChange={(event) => set('text', event.target.value)}
                    />
                    {validationErrors.text && (
                        <FieldError role="alert">
                            {validationErrors.text}
                        </FieldError>
                    )}
                </label>
            )}
            {value.type === 'image' && (
                <>
                    <label className="grid gap-1 text-sm">
                        Choose image
                        <input
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
                    </label>
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
                    <label className="grid gap-1 text-sm">
                        Provider
                        <select
                            aria-invalid={Boolean(validationErrors.provider)}
                            className="h-11 rounded-md border bg-background px-3"
                            value={value.provider}
                            onChange={(event) =>
                                set('provider', event.target.value)
                            }
                        >
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
                                <option key={provider}>{provider}</option>
                            ))}
                        </select>
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
                    </label>
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
                        <label className="grid gap-1 text-sm">
                            Spotify content type
                            <select
                                aria-invalid={Boolean(
                                    validationErrors.provider_type,
                                )}
                                className="h-11 rounded-md border bg-background px-3"
                                value={value.provider_type}
                                onChange={(event) =>
                                    set('provider_type', event.target.value)
                                }
                            >
                                {[
                                    'track',
                                    'album',
                                    'playlist',
                                    'episode',
                                    'show',
                                ].map((type) => (
                                    <option key={type}>{type}</option>
                                ))}
                            </select>
                            {validationErrors.provider_type && (
                                <FieldError role="alert">
                                    {validationErrors.provider_type}
                                </FieldError>
                            )}
                        </label>
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
        <form onSubmit={submit} className="grid gap-3 rounded-lg border p-4">
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
        <div className="grid min-w-0 gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(20rem,28rem)] xl:items-start">
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
                                className="min-h-11 border-b-2 px-4 text-sm font-medium capitalize focus-visible:outline-2 focus-visible:outline-ring"
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
                                <CardTitle>
                                    {tab === 'content'
                                        ? 'Your page'
                                        : 'Appearance'}
                                </CardTitle>
                                <CardDescription>
                                    {tab === 'content'
                                        ? 'Set your profile details and publication state.'
                                        : 'Choose the colors and type that fit your page.'}
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
                                    (
                                        [
                                            'theme',
                                            'typography',
                                            'color',
                                            'background',
                                        ] as const
                                    ).map((key) => {
                                        const options =
                                            key === 'theme'
                                                ? ['light', 'dark', 'soft']
                                                : key === 'typography'
                                                  ? ['sans', 'serif']
                                                  : key === 'color'
                                                    ? [
                                                          'blue',
                                                          'violet',
                                                          'green',
                                                          'amber',
                                                          'rose',
                                                      ]
                                                    : [
                                                          'plain',
                                                          'sand',
                                                          'lavender',
                                                          'midnight',
                                                      ];
                                        return (
                                            <label
                                                key={key}
                                                className="grid gap-1 text-sm capitalize"
                                            >
                                                {key}
                                                <select
                                                    className="h-11 rounded-md border bg-background px-3"
                                                    value={
                                                        form.data.appearance[
                                                            key
                                                        ]
                                                    }
                                                    onChange={(event) =>
                                                        form.setData(
                                                            'appearance',
                                                            {
                                                                ...form.data
                                                                    .appearance,
                                                                [key]: event
                                                                    .target
                                                                    .value,
                                                            } as BioPage['appearance'],
                                                        )
                                                    }
                                                >
                                                    {options.map((option) => (
                                                        <option key={option}>
                                                            {option}
                                                        </option>
                                                    ))}
                                                </select>
                                            </label>
                                        );
                                    })
                                )}
                            </CardContent>
                            <CardFooter>
                                <Button
                                    type="submit"
                                    disabled={form.processing}
                                >
                                    Save changes
                                </Button>
                            </CardFooter>
                        </Card>
                    </form>
                )}
                {tab === 'insights' && (
                    <section className="mt-6" aria-labelledby="insights-title">
                        <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h2
                                    id="insights-title"
                                    className="text-lg font-semibold"
                                >
                                    Insights
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    Consented page views and button or social
                                    clicks, grouped by UTC date.
                                </p>
                            </div>
                            <label className="grid gap-1 text-sm">
                                Reporting window
                                <select
                                    aria-label="Insights window"
                                    className="h-11 rounded-md border bg-background px-3"
                                    disabled={!insights}
                                    value={insights?.days ?? 30}
                                    onChange={(event) => {
                                        const days = Number(event.target.value);
                                        if (
                                            days === 7 ||
                                            days === 30 ||
                                            days === 90
                                        ) {
                                            router.get(
                                                bioPageInsightsRoute(page.id)
                                                    .url,
                                                { days },
                                                {
                                                    preserveState: true,
                                                    preserveScroll: true,
                                                },
                                            );
                                        }
                                    }}
                                >
                                    <option value={7}>7 days</option>
                                    <option value={30}>30 days</option>
                                    <option value={90}>90 days</option>
                                </select>
                            </label>
                        </div>
                        {insights ? (
                            <>
                                <div className="grid gap-3 sm:grid-cols-2">
                                    <Card>
                                        <CardHeader className="pb-2">
                                            <CardDescription>
                                                Consented page views
                                            </CardDescription>
                                        </CardHeader>
                                        <CardContent>
                                            <p className="text-3xl font-semibold tabular-nums">
                                                {insights.totalViews}
                                            </p>
                                        </CardContent>
                                    </Card>
                                    <Card>
                                        <CardHeader className="pb-2">
                                            <CardDescription>
                                                Button and social clicks
                                            </CardDescription>
                                        </CardHeader>
                                        <CardContent>
                                            <p className="text-3xl font-semibold tabular-nums">
                                                {insights.totalClicks}
                                            </p>
                                        </CardContent>
                                    </Card>
                                </div>
                                <Card className="mt-4">
                                    <CardHeader>
                                        <CardTitle>Daily activity</CardTitle>
                                        <CardDescription>
                                            {insights.startDate} through{' '}
                                            {insights.endDate} UTC
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="max-h-72 overflow-y-auto rounded-md border">
                                            <table className="w-full text-sm">
                                                <thead className="sticky top-0 bg-background text-left">
                                                    <tr>
                                                        <th className="px-3 py-2 font-medium">
                                                            UTC date
                                                        </th>
                                                        <th className="px-3 py-2 text-right font-medium">
                                                            Views
                                                        </th>
                                                        <th className="px-3 py-2 text-right font-medium">
                                                            Clicks
                                                        </th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    {insights.daily.map(
                                                        (day) => (
                                                            <tr
                                                                key={day.date}
                                                                className="border-t"
                                                            >
                                                                <td className="px-3 py-2">
                                                                    <time
                                                                        dateTime={
                                                                            day.date
                                                                        }
                                                                    >
                                                                        {
                                                                            day.date
                                                                        }
                                                                    </time>
                                                                </td>
                                                                <td className="px-3 py-2 text-right tabular-nums">
                                                                    {day.views}
                                                                </td>
                                                                <td className="px-3 py-2 text-right tabular-nums">
                                                                    {day.clicks}
                                                                </td>
                                                            </tr>
                                                        ),
                                                    )}
                                                </tbody>
                                            </table>
                                        </div>
                                    </CardContent>
                                </Card>
                            </>
                        ) : (
                            <p
                                role="status"
                                className="text-sm text-muted-foreground"
                            >
                                Loading insights…
                            </p>
                        )}
                    </section>
                )}
                {tab === 'content' && (
                    <section className="mt-6" aria-labelledby="content-title">
                        <div className="mb-3 flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h2
                                    id="content-title"
                                    className="text-lg font-semibold"
                                >
                                    Page content
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    Use the move controls or Alt + arrow keys to
                                    change order.
                                </p>
                            </div>
                            <div className="flex gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() =>
                                        setAdding(
                                            adding === 'link' ? null : 'link',
                                        )
                                    }
                                >
                                    Add button
                                </Button>
                                <Button
                                    type="button"
                                    onClick={() =>
                                        setAdding(
                                            adding === 'block' ? null : 'block',
                                        )
                                    }
                                >
                                    <Plus />
                                    Add content
                                </Button>
                            </div>
                        </div>
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
                        <ol className="mt-3 grid gap-3">
                            {items.map((item, index) => {
                                const block =
                                    item.type === 'link'
                                        ? undefined
                                        : contentBlocks.find(
                                              (candidate) =>
                                                  candidate.id === item.id,
                                          );
                                const link =
                                    item.type === 'link'
                                        ? bioLinks.find(
                                              (candidate) =>
                                                  candidate.id === item.id,
                                          )
                                        : undefined;
                                const editKey = link ? -link.id : block?.id;
                                return (
                                    <li
                                        key={`${item.type}-${item.id}`}
                                        className="rounded-lg border bg-background p-4"
                                        onKeyDown={(event) => {
                                            if (
                                                event.altKey &&
                                                event.key === 'ArrowUp' &&
                                                canMove(index, index - 1)
                                            ) {
                                                event.preventDefault();
                                                move(index, index - 1);
                                            }
                                            if (
                                                event.altKey &&
                                                event.key === 'ArrowDown' &&
                                                canMove(index, index + 1)
                                            ) {
                                                event.preventDefault();
                                                move(index, index + 1);
                                            }
                                        }}
                                    >
                                        <div className="flex flex-wrap items-start justify-between gap-3">
                                            <div className="min-w-0">
                                                <div className="flex flex-wrap gap-2">
                                                    <Badge variant="outline">
                                                        {item.type}
                                                    </Badge>
                                                    {item.isPinned && (
                                                        <Badge>Pinned</Badge>
                                                    )}
                                                    {!item.isActive && (
                                                        <Badge variant="outline">
                                                            Hidden
                                                        </Badge>
                                                    )}
                                                </div>
                                                <p className="mt-2 font-medium break-words">
                                                    {item.type === 'link'
                                                        ? item.title
                                                        : item.type === 'text'
                                                          ? item.text
                                                          : item.type ===
                                                              'image'
                                                            ? item.altText ||
                                                              'Image'
                                                            : `${item.provider}: ${item.identifier}`}
                                                </p>
                                            </div>
                                            <span className="text-sm text-muted-foreground">
                                                {index + 1}
                                            </span>
                                        </div>
                                        <div className="mt-3 flex flex-wrap gap-2">
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                aria-label="Move item up"
                                                disabled={
                                                    !canMove(index, index - 1)
                                                }
                                                onClick={() =>
                                                    move(index, index - 1)
                                                }
                                            >
                                                <ArrowUp />
                                                Move up
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                aria-label="Move item down"
                                                disabled={
                                                    !canMove(index, index + 1)
                                                }
                                                onClick={() =>
                                                    move(index, index + 1)
                                                }
                                            >
                                                <ArrowDown />
                                                Move down
                                            </Button>
                                            {link && (
                                                <>
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={() =>
                                                            setEditing(
                                                                editing ===
                                                                    editKey
                                                                    ? null
                                                                    : (editKey ??
                                                                          null),
                                                            )
                                                        }
                                                    >
                                                        Edit button
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={() =>
                                                            router.patch(
                                                                updateBioLink({
                                                                    bioPage:
                                                                        page.id,
                                                                    bioLink:
                                                                        link.id,
                                                                }).url,
                                                                {
                                                                    is_active:
                                                                        !link.is_active,
                                                                },
                                                                {
                                                                    preserveScroll: true,
                                                                },
                                                            )
                                                        }
                                                    >
                                                        {link.is_active
                                                            ? 'Disable'
                                                            : 'Enable'}
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        variant="destructive"
                                                        size="sm"
                                                        onClick={() => {
                                                            if (
                                                                window.confirm(
                                                                    'Delete this button?',
                                                                )
                                                            )
                                                                router.delete(
                                                                    destroyBioLink(
                                                                        {
                                                                            bioPage:
                                                                                page.id,
                                                                            bioLink:
                                                                                link.id,
                                                                        },
                                                                    ).url,
                                                                    {
                                                                        preserveScroll: true,
                                                                    },
                                                                );
                                                        }}
                                                    >
                                                        <Trash2 />
                                                        Delete
                                                    </Button>
                                                </>
                                            )}
                                            {block && (
                                                <>
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={() =>
                                                            setEditing(
                                                                editing ===
                                                                    editKey
                                                                    ? null
                                                                    : (editKey ??
                                                                          null),
                                                            )
                                                        }
                                                    >
                                                        Edit
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        variant="destructive"
                                                        size="sm"
                                                        onClick={() => {
                                                            if (
                                                                window.confirm(
                                                                    'Delete this content block?',
                                                                )
                                                            )
                                                                router.delete(
                                                                    destroyBioBlock(
                                                                        {
                                                                            bioPage:
                                                                                page.id,
                                                                            bioBlock:
                                                                                block.id,
                                                                        },
                                                                    ).url,
                                                                    {
                                                                        preserveScroll: true,
                                                                    },
                                                                );
                                                        }}
                                                    >
                                                        <Trash2 />
                                                        Delete
                                                    </Button>
                                                </>
                                            )}
                                        </div>
                                        {link && editing === editKey && (
                                            <div className="mt-3">
                                                <LinkForm
                                                    page={page}
                                                    link={link}
                                                    onClose={() =>
                                                        setEditing(null)
                                                    }
                                                />
                                            </div>
                                        )}
                                        {block && editing === editKey && (
                                            <div className="mt-3">
                                                <BlockFormEditor
                                                    page={page}
                                                    block={block}
                                                    onClose={() =>
                                                        setEditing(null)
                                                    }
                                                />
                                            </div>
                                        )}
                                    </li>
                                );
                            })}
                        </ol>
                        {items.length === 0 && (
                            <p className="mt-3 rounded-lg border border-dashed p-5 text-sm text-muted-foreground">
                                Add a button or content block to start your
                                page.
                            </p>
                        )}
                    </section>
                )}
            </div>
            <aside className="xl:sticky xl:top-6">
                <LivePreview
                    presentation={presentation}
                    isPublished={page.isPublished}
                    previewAt={previewAt}
                />
            </aside>
        </div>
    );
}
