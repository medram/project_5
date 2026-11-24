import { useForm } from '@inertiajs/react';
import {
    CalendarClock,
    Link2,
    LockKeyhole,
    Palette,
    QrCode,
    Share2,
    type LucideIcon,
} from 'lucide-react';
import {
    type FormEvent,
    type ReactNode,
    useEffect,
    useRef,
    useState,
} from 'react';
import { useContentEditorRoutes } from '@/components/admin/content-editor-routes';
import { FormErrorSummary } from '@/components/forms/form-error-summary';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import {
    Field,
    FieldDescription,
    FieldError,
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
import { Switch } from '@/components/ui/switch';
import { cn } from '@/lib/utils';
import { draftPreview, preview, store, update } from '@/routes/qr-codes';
import type {
    QrCodeCampaign,
    QrCodeIndexProps,
    QrCodeLinkOption,
} from '@/types/qr-codes';
import {
    type QrCodeFormData,
    qrCodeFormData,
    toServerDate,
} from './qr-code-form-data';
import {
    QrCodeSettingsDialogs,
    type QrSettingsPanel,
} from './qr-code-settings-dialogs';

type ErrorTarget = {
    panel?: QrSettingsPanel;
    id?: string;
};

const styleTargets: Record<string, string> = {
    foreground_color: 'qr-foreground',
    background_color: 'qr-background',
    error_correction: 'qr-correction',
    margin: 'qr-margin',
    size: 'qr-size',
    logo: 'qr-logo',
    logo_alt: 'qr-logo-alt',
    clear_logo: 'qr-logo',
};

function errorTarget(key: string): ErrorTarget {
    if (key === 'destination_url') return { id: 'qr-destination-url' };
    if (key === 'link_id') return { id: 'qr-link-id' };
    if (key === 'title') return { id: 'qr-title' };
    if (key === 'is_active') return { id: 'qr-active' };
    if (key === 'share_title') {
        return { panel: 'sharing', id: 'qr-share-title' };
    }
    if (key === 'share_description') {
        return { panel: 'sharing', id: 'qr-share-description' };
    }
    if (styleTargets[key]) {
        return { panel: 'style', id: styleTargets[key] };
    }
    if (key === 'access_code') {
        return { panel: 'access', id: 'qr-access-code' };
    }
    if (key === 'remove_access_code') {
        return { panel: 'access', id: 'qr-remove-access-code' };
    }
    if (
        key === 'available_from' ||
        key === 'expires_at' ||
        key === 'max_scans'
    ) {
        return {
            panel: 'schedule',
            id: `qr-${key.replaceAll('_', '-')}`,
        };
    }

    return {};
}

function errorLabel(key: string): string {
    if (key === 'destination_url') return 'Destination URL';
    if (key === 'link_id') return 'Short link';
    if (key === 'title') return 'Title';
    if (key === 'is_active') return 'Status';
    if (key === 'share_title' || key === 'share_description') return 'Sharing';
    if (styleTargets[key]) return 'Style';
    if (key === 'access_code' || key === 'remove_access_code') return 'Access';
    if (
        key === 'available_from' ||
        key === 'expires_at' ||
        key === 'max_scans'
    ) {
        return 'Schedule';
    }

    return key
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (character) => character.toUpperCase());
}

function FieldMessage({
    id,
    children,
}: {
    id: string;
    children: string | undefined;
}) {
    return children ? <FieldError id={id}>{children}</FieldError> : null;
}

function QrPreviewPanel({
    src,
    logoUrl,
    logoAlt,
    title,
    previewSize,
    previewMargin,
    isSaved,
    code,
    active,
    onActiveChange,
    disabled,
    activeError,
}: {
    src: string;
    logoUrl: string | null;
    logoAlt: string | null;
    title: string;
    previewSize: number;
    previewMargin: number;
    isSaved: boolean;
    code: string | null;
    active: boolean;
    onActiveChange: (active: boolean) => void;
    disabled: boolean;
    activeError: string | undefined;
}) {
    const alt = [
        `QR code preview for ${title || 'this campaign'}`,
        logoAlt ? `Includes logo: ${logoAlt}` : null,
    ]
        .filter(Boolean)
        .join('. ');

    return (
        <div className="grid min-w-0 content-start gap-4">
            <div className="grid gap-3">
                <div className="flex items-center justify-between gap-3">
                    <p className="text-sm font-medium">QR code preview</p>
                    <span className="text-xs text-muted-foreground">
                        {isSaved ? 'Saved' : 'Draft'}
                    </span>
                </div>
                <div className="rounded-2xl border border-border/70 bg-surface-secondary p-3">
                    <div className="relative mx-auto aspect-square w-full max-w-40 sm:max-w-48">
                        <img
                            src={src}
                            alt={alt}
                            className="size-full object-contain"
                        />
                        {logoUrl && (
                            <img
                                src={logoUrl}
                                alt=""
                                aria-hidden="true"
                                className="absolute top-1/2 left-1/2 aspect-square -translate-x-1/2 -translate-y-1/2 rounded bg-white object-contain p-1"
                                style={{
                                    width: `${(Math.min(128, Math.floor(previewSize / 4)) / previewSize) * 100}%`,
                                }}
                            />
                        )}
                    </div>
                </div>
                <p className="text-xs leading-5 text-muted-foreground">
                    {isSaved && code
                        ? `Encodes the saved /q/${code} campaign URL. Download formats are on the campaign card.`
                        : 'This draft is not scannable. Save to create a scan-ready QR code.'}
                </p>
            </div>

            <div className="rounded-xl border border-border/70 p-3">
                <div className="flex items-center justify-between gap-3">
                    <div className="grid gap-0.5">
                        <FieldLabel htmlFor="qr-active" className="text-sm">
                            Active
                        </FieldLabel>
                        <p
                            id="qr-active-help"
                            className="text-xs text-muted-foreground"
                        >
                            Paused codes reject scans without counting them.
                        </p>
                    </div>
                    <Switch
                        id="qr-active"
                        checked={active}
                        onCheckedChange={onActiveChange}
                        disabled={disabled}
                        aria-describedby="qr-active-help"
                        aria-invalid={Boolean(activeError)}
                    />
                </div>
                {activeError && (
                    <FieldError className="mt-2">{activeError}</FieldError>
                )}
            </div>

            {previewMargin < 2 && (
                <p className="rounded-lg bg-warning-soft px-3 py-2 text-xs leading-5 text-warning-soft-foreground">
                    A quiet-zone margin below 2 can make the code harder to
                    scan.
                </p>
            )}
            {previewSize < 160 && (
                <p className="rounded-lg bg-warning-soft px-3 py-2 text-xs leading-5 text-warning-soft-foreground">
                    Small downloads may be hard to scan at print size.
                </p>
            )}
        </div>
    );
}

export function QrCodeFormDialog({
    children,
    campaign,
    availableLinks,
    open: controlledOpen,
    onOpenChange,
}: {
    children?: ReactNode;
    campaign?: QrCodeCampaign;
    availableLinks: QrCodeLinkOption[];
    open?: boolean;
    onOpenChange?: (open: boolean) => void;
}) {
    const [internalOpen, setInternalOpen] = useState(false);
    const open = controlledOpen ?? internalOpen;
    const { editorUrl, isAdmin } = useContentEditorRoutes();
    const [settingsPanel, setSettingsPanel] = useState<QrSettingsPanel | null>(
        null,
    );
    const form = useForm<QrCodeFormData>(qrCodeFormData(campaign));
    const settingsTriggerRef = useRef<HTMLButtonElement | null>(null);
    const errorSummaryRef = useRef<HTMLDivElement | null>(null);
    const errors = form.errors as Record<string, string | undefined>;
    const errorEntries = Object.entries(errors).filter(
        (entry): entry is [string, string] => Boolean(entry[1]),
    );
    const [errorFocusKey, setErrorFocusKey] = useState(0);
    const [logoPreviewUrl, setLogoPreviewUrl] = useState<string | null>(null);

    useEffect(() => {
        if (errorFocusKey === 0 || errorEntries.length === 0) {
            return;
        }

        const timeout = window.setTimeout(
            () => errorSummaryRef.current?.focus(),
            100,
        );

        return () => window.clearTimeout(timeout);
    }, [errorFocusKey, errorEntries.length]);

    useEffect(() => {
        const file = form.data.logo;
        if (!file) {
            setLogoPreviewUrl(null);
            return;
        }

        const objectUrl = URL.createObjectURL(file);
        setLogoPreviewUrl(objectUrl);

        return () => URL.revokeObjectURL(objectUrl);
    }, [form.data.logo]);

    const parsedPreviewSize = Number(form.data.size);
    const previewSize =
        Number.isInteger(parsedPreviewSize) &&
        parsedPreviewSize >= 128 &&
        parsedPreviewSize <= 1024
            ? parsedPreviewSize
            : 320;
    const parsedPreviewMargin = Number(form.data.margin);
    const previewMargin =
        Number.isInteger(parsedPreviewMargin) &&
        parsedPreviewMargin >= 0 &&
        parsedPreviewMargin <= 20
            ? parsedPreviewMargin
            : 4;
    const ownerDraftPreviewUrl = draftPreview({
        query: {
            foreground_color: /^#[0-9a-f]{6}$/i.test(form.data.foreground_color)
                ? form.data.foreground_color
                : '#000000',
            background_color: /^#[0-9a-f]{6}$/i.test(form.data.background_color)
                ? form.data.background_color
                : '#FFFFFF',
            error_correction: form.data.error_correction,
            margin: previewMargin,
            size: previewSize,
        },
    }).url;
    const draftPreviewUrl =
        isAdmin && campaign
            ? editorUrl(
                  `${update(campaign.id).url}/draft-preview.svg${ownerDraftPreviewUrl.slice(ownerDraftPreviewUrl.indexOf('?'))}`,
              )
            : ownerDraftPreviewUrl;
    const linkedQr = campaign?.source === 'linked';
    const previewSrc = campaign
        ? editorUrl(preview(campaign.id).url)
        : draftPreviewUrl;
    const previewLogoAlt =
        form.data.logo_alt ||
        (campaign?.has_logo && !form.data.clear_logo
            ? campaign.logo_alt
            : null);
    const title = form.data.title || campaign?.title || 'QR campaign';

    const sharingConfigured =
        form.data.share_title.trim() !== '' ||
        form.data.share_description.trim() !== '';
    const styleConfigured =
        Boolean(form.data.logo) ||
        (Boolean(campaign?.has_logo) && !form.data.clear_logo) ||
        form.data.foreground_color.toUpperCase() !== '#000000' ||
        form.data.background_color.toUpperCase() !== '#FFFFFF' ||
        form.data.error_correction !== 'medium' ||
        form.data.margin !== '4' ||
        form.data.size !== '320';
    const accessConfigured =
        form.data.access_code !== '' ||
        Boolean(campaign?.has_access_code && !form.data.remove_access_code);
    const scheduleConfigured =
        form.data.available_from !== '' ||
        form.data.expires_at !== '' ||
        form.data.max_scans !== '';
    const settingsActions: Array<{
        panel: QrSettingsPanel;
        label: string;
        icon: LucideIcon;
        configured: boolean;
    }> = [
        {
            panel: 'sharing',
            label: 'Sharing',
            icon: Share2,
            configured: sharingConfigured,
        },
        {
            panel: 'style',
            label: 'Style',
            icon: Palette,
            configured: styleConfigured,
        },
        {
            panel: 'access',
            label: 'Access',
            icon: LockKeyhole,
            configured: accessConfigured,
        },
        {
            panel: 'schedule',
            label: 'Schedule',
            icon: CalendarClock,
            configured: scheduleConfigured,
        },
    ];

    function handleOpenChange(nextOpen: boolean) {
        if (form.processing) {
            return;
        }

        setInternalOpen(nextOpen);
        onOpenChange?.(nextOpen);

        if (nextOpen) {
            form.resetAndClearErrors();
            setSettingsPanel(null);
        }
    }

    function openSettings(panel: QrSettingsPanel, focusId?: string) {
        settingsTriggerRef.current = document.querySelector<HTMLButtonElement>(
            `[data-settings-panel="${panel}"]`,
        );
        setSettingsPanel(panel);

        if (focusId) {
            window.setTimeout(
                () => document.getElementById(focusId)?.focus(),
                0,
            );
        }
    }

    function handleErrorEntry(key: string) {
        const target = errorTarget(key);

        if (target.panel) {
            openSettings(target.panel, target.id);
        } else if (target.id) {
            document.getElementById(target.id)?.focus();
        }
    }

    function commitSharing(
        next: Pick<QrCodeFormData, 'share_title' | 'share_description'>,
    ) {
        form.setData({ ...form.data, ...next });
        form.clearErrors('share_title');
        form.clearErrors('share_description');
        setSettingsPanel(null);
    }

    function commitStyle(
        next: Pick<
            QrCodeFormData,
            | 'foreground_color'
            | 'background_color'
            | 'error_correction'
            | 'margin'
            | 'size'
            | 'logo'
            | 'logo_alt'
            | 'clear_logo'
        >,
    ) {
        form.setData({ ...form.data, ...next });
        form.clearErrors('foreground_color');
        form.clearErrors('background_color');
        form.clearErrors('error_correction');
        form.clearErrors('margin');
        form.clearErrors('size');
        form.clearErrors('logo');
        form.clearErrors('logo_alt');
        form.clearErrors('clear_logo');
        setSettingsPanel(null);
    }

    function commitAccess(
        next: Pick<QrCodeFormData, 'access_code' | 'remove_access_code'>,
    ) {
        form.setData({ ...form.data, ...next });
        form.clearErrors('access_code');
        form.clearErrors('remove_access_code');
        setSettingsPanel(null);
    }

    function commitSchedule(
        next: Pick<
            QrCodeFormData,
            'available_from' | 'expires_at' | 'max_scans'
        >,
    ) {
        form.setData({ ...form.data, ...next });
        form.clearErrors('available_from');
        form.clearErrors('expires_at');
        form.clearErrors('max_scans');
        setSettingsPanel(null);
    }

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.transform((data) => ({
            title: data.title.trim() || null,
            share_title: data.share_title.trim() || null,
            share_description: data.share_description.trim() || null,
            is_active: data.is_active,
            available_from: toServerDate(data.available_from),
            expires_at: toServerDate(data.expires_at),
            max_scans: data.max_scans === '' ? null : Number(data.max_scans),
            access_code: data.access_code || null,
            remove_access_code: data.remove_access_code,
            foreground_color: data.foreground_color,
            background_color: data.background_color,
            error_correction: data.error_correction,
            margin: Number(data.margin),
            size: Number(data.size),
            logo: data.logo,
            logo_alt: data.logo_alt.trim() || null,
            clear_logo: data.clear_logo,
            ...(linkedQr
                ? {}
                : {
                      link_id:
                          data.target_type === 'link' && data.link_id !== ''
                              ? Number(data.link_id)
                              : null,
                      destination_url:
                          data.target_type === 'url'
                              ? data.destination_url.trim() || null
                              : null,
                  }),
            ...(campaign ? { _method: 'PATCH' as const } : {}),
        }));
        form.post(campaign ? editorUrl(update(campaign.id).url) : store().url, {
            preserveScroll: true,
            onSuccess: (page) => {
                const savedCampaign = campaign
                    ? ((
                          page.props as unknown as Partial<QrCodeIndexProps>
                      ).campaigns?.find((item) => item.id === campaign.id) ??
                      campaign)
                    : undefined;
                form.setDefaults(qrCodeFormData(savedCampaign));
                form.reset();
                setInternalOpen(false);
                onOpenChange?.(false);
            },
            onError: () => {
                setErrorFocusKey((key) => key + 1);
            },
        });
    }

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            {children && <DialogTrigger asChild>{children}</DialogTrigger>}
            <DialogContent
                className="flex max-h-[calc(100dvh-2rem)] flex-col gap-0 overflow-hidden p-0 sm:max-w-[52rem]"
                onOpenAutoFocus={(event) => {
                    event.preventDefault();

                    if (window.matchMedia('(max-width: 640px)').matches) {
                        document
                            .getElementById('qr-form-dialog-title')
                            ?.focus();

                        return;
                    }

                    const target =
                        form.data.target_type === 'url'
                            ? 'qr-destination-url'
                            : 'qr-link-id';
                    document.getElementById(target)?.focus();
                }}
            >
                <DialogHeader className="shrink-0 px-6 pt-6 pr-14 pb-4">
                    <DialogTitle
                        id="qr-form-dialog-title"
                        tabIndex={-1}
                        className="flex items-center gap-2"
                    >
                        <QrCode className="size-4" aria-hidden="true" />
                        {campaign ? 'Edit QR code' : 'Create QR code'}
                    </DialogTitle>
                    <DialogDescription className="sr-only">
                        Set a destination, then configure optional sharing,
                        style, access, and schedule controls.
                    </DialogDescription>
                </DialogHeader>

                <FormErrorSummary
                    summaryRef={errorSummaryRef}
                    titleId="qr-form-errors-title"
                    entries={errorEntries.map(([key, message]) => {
                        const target = errorTarget(key);

                        return {
                            key,
                            label: errorLabel(key),
                            message,
                            interactive: Boolean(target.panel || target.id),
                        };
                    })}
                    onSelect={handleErrorEntry}
                />

                <form
                    onSubmit={submit}
                    className="flex min-h-0 flex-1 flex-col"
                >
                    <div className="min-h-0 flex-1 overflow-y-auto px-6 py-5">
                        <div className="grid min-w-0 gap-6 md:grid-cols-[minmax(0,1fr)_17rem]">
                            <div className="flex min-w-0 flex-col gap-5">
                                {linkedQr ? (
                                    <Field>
                                        <FieldLabel>
                                            Short-link target
                                        </FieldLabel>
                                        <div className="flex items-center gap-2 rounded-xl border border-border/70 bg-muted/30 px-3 py-3 text-sm">
                                            <Link2
                                                className="size-4 shrink-0 text-muted-foreground"
                                                aria-hidden="true"
                                            />
                                            <span className="min-w-0 truncate">
                                                /{campaign?.link?.slug}
                                            </span>
                                        </div>
                                        <FieldDescription>
                                            This QR stays attached to the short
                                            link. Change its destination from
                                            the link settings.
                                        </FieldDescription>
                                    </Field>
                                ) : (
                                    <>
                                        <Field
                                            data-invalid={Boolean(
                                                errors.destination_url ||
                                                errors.link_id,
                                            )}
                                        >
                                            <FieldLabel htmlFor="qr-target-type">
                                                Destination type
                                            </FieldLabel>
                                            <Select
                                                value={form.data.target_type}
                                                onValueChange={(
                                                    value: 'url' | 'link',
                                                ) => {
                                                    form.setData(
                                                        'target_type',
                                                        value,
                                                    );
                                                    form.clearErrors(
                                                        'destination_url',
                                                    );
                                                    form.clearErrors('link_id');
                                                }}
                                                disabled={form.processing}
                                            >
                                                <SelectTrigger
                                                    id="qr-target-type"
                                                    className="h-10 w-full"
                                                >
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="url">
                                                        Direct URL
                                                    </SelectItem>
                                                    <SelectItem value="link">
                                                        Workspace short link
                                                    </SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </Field>

                                        {form.data.target_type === 'url' ? (
                                            <Field
                                                data-invalid={Boolean(
                                                    errors.destination_url,
                                                )}
                                            >
                                                <FieldLabel htmlFor="qr-destination-url">
                                                    Destination URL
                                                </FieldLabel>
                                                <Input
                                                    id="qr-destination-url"
                                                    type="url"
                                                    value={
                                                        form.data
                                                            .destination_url
                                                    }
                                                    onChange={(event) =>
                                                        form.setData(
                                                            'destination_url',
                                                            event.target.value,
                                                        )
                                                    }
                                                    placeholder="https://example.com/menu"
                                                    maxLength={2048}
                                                    autoComplete="url"
                                                    disabled={form.processing}
                                                    required
                                                    aria-invalid={Boolean(
                                                        errors.destination_url,
                                                    )}
                                                    aria-describedby={
                                                        errors.destination_url
                                                            ? 'qr-destination-url-error'
                                                            : 'qr-destination-help'
                                                    }
                                                />
                                                <FieldDescription id="qr-destination-help">
                                                    Use an HTTP or HTTPS URL.
                                                    The destination cannot
                                                    target this application.
                                                </FieldDescription>
                                                <FieldMessage id="qr-destination-url-error">
                                                    {errors.destination_url}
                                                </FieldMessage>
                                            </Field>
                                        ) : (
                                            <Field
                                                data-invalid={Boolean(
                                                    errors.link_id,
                                                )}
                                            >
                                                <FieldLabel htmlFor="qr-link-id">
                                                    Short link
                                                </FieldLabel>
                                                <Select
                                                    value={form.data.link_id}
                                                    onValueChange={(value) =>
                                                        form.setData(
                                                            'link_id',
                                                            value,
                                                        )
                                                    }
                                                    disabled={
                                                        form.processing ||
                                                        availableLinks.length ===
                                                            0
                                                    }
                                                >
                                                    <SelectTrigger
                                                        id="qr-link-id"
                                                        className="h-10 w-full"
                                                        aria-invalid={Boolean(
                                                            errors.link_id,
                                                        )}
                                                        aria-describedby={
                                                            errors.link_id
                                                                ? 'qr-link-id-error'
                                                                : undefined
                                                        }
                                                    >
                                                        <SelectValue placeholder="Select a workspace link" />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {availableLinks.map(
                                                            (link) => (
                                                                <SelectItem
                                                                    key={
                                                                        link.id
                                                                    }
                                                                    value={String(
                                                                        link.id,
                                                                    )}
                                                                >
                                                                    {link.title ||
                                                                        `/${link.slug}`}{' '}
                                                                    — /
                                                                    {link.slug}
                                                                </SelectItem>
                                                            ),
                                                        )}
                                                    </SelectContent>
                                                </Select>
                                                {availableLinks.length ===
                                                    0 && (
                                                    <FieldDescription>
                                                        Create a short link in
                                                        this workspace first.
                                                    </FieldDescription>
                                                )}
                                                <FieldMessage id="qr-link-id-error">
                                                    {errors.link_id}
                                                </FieldMessage>
                                            </Field>
                                        )}
                                    </>
                                )}

                                <Field data-invalid={Boolean(errors.title)}>
                                    <FieldLabel htmlFor="qr-title">
                                        Title{' '}
                                        <span className="font-normal text-muted-foreground">
                                            (optional)
                                        </span>
                                    </FieldLabel>
                                    <Input
                                        id="qr-title"
                                        value={form.data.title}
                                        onChange={(event) =>
                                            form.setData(
                                                'title',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="e.g. Spring menu"
                                        maxLength={160}
                                        disabled={form.processing}
                                        aria-invalid={Boolean(errors.title)}
                                        aria-describedby={
                                            errors.title
                                                ? 'qr-title-error'
                                                : undefined
                                        }
                                    />
                                    <FieldDescription>
                                        Helps you identify this campaign in the
                                        QR list.
                                    </FieldDescription>
                                    <FieldMessage id="qr-title-error">
                                        {errors.title}
                                    </FieldMessage>
                                </Field>
                            </div>

                            <QrPreviewPanel
                                src={previewSrc}
                                logoUrl={logoPreviewUrl}
                                logoAlt={previewLogoAlt}
                                title={title}
                                previewSize={previewSize}
                                previewMargin={previewMargin}
                                isSaved={Boolean(campaign)}
                                code={campaign?.code ?? null}
                                active={form.data.is_active}
                                onActiveChange={(active) =>
                                    form.setData('is_active', active)
                                }
                                disabled={form.processing}
                                activeError={errors.is_active}
                            />
                        </div>
                    </div>

                    <div className="shrink-0 border-t border-border bg-card px-6 py-4">
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div className="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap">
                                {settingsActions.map(
                                    ({
                                        panel,
                                        label,
                                        icon: Icon,
                                        configured,
                                    }) => {
                                        const hasError = errorEntries.some(
                                            ([key]) =>
                                                errorTarget(key).panel ===
                                                panel,
                                        );

                                        return (
                                            <Button
                                                key={panel}
                                                ref={settingsTriggerRef}
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                data-settings-panel={panel}
                                                className={cn(
                                                    'h-10 justify-start sm:h-9',
                                                    configured &&
                                                        'border-primary/20 bg-primary-soft text-primary-soft-foreground hover:bg-primary-soft/80',
                                                    hasError &&
                                                        'border-danger/40',
                                                )}
                                                aria-haspopup="dialog"
                                                aria-label={`${label}${configured ? ', configured' : ''}${hasError ? ', has an error' : ''}`}
                                                onClick={(event) => {
                                                    settingsTriggerRef.current =
                                                        event.currentTarget;
                                                    openSettings(panel);
                                                }}
                                                disabled={form.processing}
                                            >
                                                <Icon aria-hidden="true" />
                                                {label}
                                            </Button>
                                        );
                                    },
                                )}
                            </div>
                            <Button
                                type="submit"
                                className="h-10 w-full sm:w-auto"
                                disabled={form.processing}
                            >
                                {form.processing && (
                                    <Spinner data-icon="inline-start" />
                                )}
                                {campaign ? 'Save changes' : 'Create QR code'}
                            </Button>
                        </div>
                    </div>
                </form>

                <QrCodeSettingsDialogs
                    activePanel={settingsPanel}
                    onActivePanelChange={setSettingsPanel}
                    settingsTriggerRef={settingsTriggerRef}
                    data={form.data}
                    errors={errors}
                    processing={form.processing}
                    hasExistingAccessCode={campaign?.has_access_code ?? false}
                    hasExistingLogo={campaign?.has_logo ?? false}
                    onCommitSharing={commitSharing}
                    onCommitStyle={commitStyle}
                    onCommitAccess={commitAccess}
                    onCommitSchedule={commitSchedule}
                />
            </DialogContent>
        </Dialog>
    );
}
