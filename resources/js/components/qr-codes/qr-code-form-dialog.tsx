import { useForm } from '@inertiajs/react';
import { type FormEvent, type ReactNode, useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
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
import { Textarea } from '@/components/ui/textarea';
import { draftPreview, preview, store, update } from '@/routes/qr-codes';
import type {
    QrCodeCampaign,
    QrCodeIndexProps,
    QrCodeLinkOption,
} from '@/types/qr-codes';

type FormData = {
    target_type: 'url' | 'link';
    link_id: string;
    destination_url: string;
    title: string;
    share_title: string;
    share_description: string;
    is_active: boolean;
    available_from: string;
    expires_at: string;
    max_scans: string;
    access_code: string;
    remove_access_code: boolean;
    foreground_color: string;
    background_color: string;
    error_correction: 'low' | 'medium' | 'quartile' | 'high';
    margin: string;
    size: string;
    logo: File | null;
    logo_alt: string;
    clear_logo: boolean;
    _method?: 'PATCH';
};

function localDateTime(value: string | null): string {
    if (!value) return '';
    const date = new Date(value);
    return new Date(date.getTime() - date.getTimezoneOffset() * 60_000)
        .toISOString()
        .slice(0, 16);
}

function initialData(campaign?: QrCodeCampaign): FormData {
    return {
        target_type: campaign?.link_id ? 'link' : 'url',
        link_id: campaign?.link_id ? String(campaign.link_id) : '',
        destination_url: campaign?.destination_url ?? '',
        title: campaign?.title ?? '',
        share_title: campaign?.share_title ?? '',
        share_description: campaign?.share_description ?? '',
        is_active: campaign?.is_active ?? true,
        available_from: localDateTime(campaign?.available_from ?? null),
        expires_at: localDateTime(campaign?.expires_at ?? null),
        max_scans: campaign?.max_scans ? String(campaign.max_scans) : '',
        access_code: '',
        remove_access_code: false,
        foreground_color: campaign?.foreground_color ?? '#000000',
        background_color: campaign?.background_color ?? '#FFFFFF',
        error_correction: campaign?.error_correction ?? 'medium',
        margin: String(campaign?.margin ?? 4),
        size: String(campaign?.size ?? 320),
        logo: null,
        logo_alt: campaign?.logo_alt ?? '',
        clear_logo: false,
    };
}

function toServerDate(value: string): string | null {
    return value === '' ? null : new Date(value).toISOString();
}

function FieldMessage({ children }: { children: string | undefined }) {
    return children ? <FieldError>{children}</FieldError> : null;
}

export function QrCodeFormDialog({
    children,
    campaign,
    availableLinks,
}: {
    children: ReactNode;
    campaign?: QrCodeCampaign;
    availableLinks: QrCodeLinkOption[];
}) {
    const [open, setOpen] = useState(false);
    const form = useForm<FormData>(initialData(campaign));
    const [logoPreviewUrl, setLogoPreviewUrl] = useState<string | null>(null);
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
    const draftPreviewUrl = draftPreview({
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
    const linkedQr = campaign?.source === 'linked';
    const planError = (form.errors as typeof form.errors & { plan?: string })
        .plan;

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
        form.post(campaign ? update(campaign.id).url : store().url, {
            preserveScroll: true,
            onSuccess: (page) => {
                const savedCampaign = campaign
                    ? ((
                          page.props as unknown as QrCodeIndexProps
                      ).campaigns.find((item) => item.id === campaign.id) ??
                      campaign)
                    : undefined;
                form.setDefaults(initialData(savedCampaign));
                form.reset();
                setOpen(false);
            },
        });
    }

    return (
        <Dialog
            open={open}
            onOpenChange={(nextOpen) => {
                if (nextOpen) form.resetAndClearErrors();
                setOpen(nextOpen);
            }}
        >
            <DialogTrigger asChild>{children}</DialogTrigger>
            <DialogContent className="max-h-[90dvh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>
                        {campaign ? 'Edit QR campaign' : 'Create QR campaign'}
                    </DialogTitle>
                    <DialogDescription>
                        Campaign scans are counted separately from short-link
                        clicks. The encoded QR URL stays stable when campaign
                        settings change.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={submit} className="space-y-6">
                    <FieldGroup>
                        {!linkedQr && (
                            <Field
                                data-invalid={Boolean(
                                    form.errors.destination_url ||
                                    form.errors.link_id,
                                )}
                            >
                                <FieldLabel htmlFor="qr-target-type">
                                    Destination type
                                </FieldLabel>
                                <Select
                                    value={form.data.target_type}
                                    onValueChange={(value: 'url' | 'link') =>
                                        form.setData('target_type', value)
                                    }
                                    disabled={form.processing}
                                >
                                    <SelectTrigger id="qr-target-type">
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
                                {form.data.target_type === 'url' ? (
                                    <>
                                        <FieldLabel htmlFor="qr-destination-url">
                                            Destination URL
                                        </FieldLabel>
                                        <Input
                                            id="qr-destination-url"
                                            type="url"
                                            value={form.data.destination_url}
                                            onChange={(event) =>
                                                form.setData(
                                                    'destination_url',
                                                    event.target.value,
                                                )
                                            }
                                            placeholder="https://example.com/menu"
                                            maxLength={2048}
                                            disabled={form.processing}
                                            aria-invalid={Boolean(
                                                form.errors.destination_url,
                                            )}
                                        />
                                    </>
                                ) : (
                                    <>
                                        <FieldLabel htmlFor="qr-link-id">
                                            Short link
                                        </FieldLabel>
                                        <Select
                                            value={form.data.link_id}
                                            onValueChange={(value) =>
                                                form.setData('link_id', value)
                                            }
                                            disabled={
                                                form.processing ||
                                                availableLinks.length === 0
                                            }
                                        >
                                            <SelectTrigger
                                                id="qr-link-id"
                                                aria-invalid={Boolean(
                                                    form.errors.link_id,
                                                )}
                                            >
                                                <SelectValue placeholder="Select a workspace link" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {availableLinks.map((link) => (
                                                    <SelectItem
                                                        key={link.id}
                                                        value={String(link.id)}
                                                    >
                                                        {link.title ||
                                                            `/${link.slug}`}{' '}
                                                        — /{link.slug}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        {availableLinks.length === 0 && (
                                            <FieldDescription>
                                                Create a short link in this
                                                workspace first.
                                            </FieldDescription>
                                        )}
                                    </>
                                )}
                                <FieldDescription>
                                    Direct URLs must use HTTP or HTTPS and
                                    cannot target this LinkPilot application.
                                </FieldDescription>
                                <FieldMessage>
                                    {form.errors.destination_url}
                                </FieldMessage>
                                <FieldMessage>
                                    {form.errors.link_id}
                                </FieldMessage>
                            </Field>
                        )}

                        {linkedQr && (
                            <Field>
                                <FieldLabel>Short-link target</FieldLabel>
                                <FieldDescription>
                                    This QR remains attached to /
                                    {campaign.link?.slug}. Change the short
                                    link's destination from its link settings.
                                </FieldDescription>
                            </Field>
                        )}

                        <Field data-invalid={Boolean(form.errors.title)}>
                            <FieldLabel htmlFor="qr-title">
                                Campaign name
                            </FieldLabel>
                            <Input
                                id="qr-title"
                                value={form.data.title}
                                onChange={(event) =>
                                    form.setData('title', event.target.value)
                                }
                                maxLength={160}
                                disabled={form.processing}
                            />
                            <FieldMessage>{form.errors.title}</FieldMessage>
                        </Field>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field
                                data-invalid={Boolean(form.errors.share_title)}
                            >
                                <FieldLabel htmlFor="qr-share-title">
                                    Sharing title
                                </FieldLabel>
                                <Input
                                    id="qr-share-title"
                                    value={form.data.share_title}
                                    onChange={(event) =>
                                        form.setData(
                                            'share_title',
                                            event.target.value,
                                        )
                                    }
                                    maxLength={160}
                                    disabled={form.processing}
                                />
                                <FieldMessage>
                                    {form.errors.share_title}
                                </FieldMessage>
                            </Field>
                            <Field
                                data-invalid={Boolean(form.errors.max_scans)}
                            >
                                <FieldLabel htmlFor="qr-max-scans">
                                    Maximum scans
                                </FieldLabel>
                                <Input
                                    id="qr-max-scans"
                                    type="number"
                                    min={1}
                                    step={1}
                                    value={form.data.max_scans}
                                    onChange={(event) =>
                                        form.setData(
                                            'max_scans',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="Unlimited"
                                    disabled={form.processing}
                                />
                                <FieldMessage>
                                    {form.errors.max_scans}
                                </FieldMessage>
                            </Field>
                        </div>

                        <Field
                            data-invalid={Boolean(
                                form.errors.share_description,
                            )}
                        >
                            <FieldLabel htmlFor="qr-share-description">
                                Sharing description
                            </FieldLabel>
                            <Textarea
                                id="qr-share-description"
                                value={form.data.share_description}
                                onChange={(event) =>
                                    form.setData(
                                        'share_description',
                                        event.target.value,
                                    )
                                }
                                maxLength={2000}
                                rows={3}
                                disabled={form.processing}
                            />
                            <FieldMessage>
                                {form.errors.share_description}
                            </FieldMessage>
                        </Field>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field
                                data-invalid={Boolean(
                                    form.errors.available_from,
                                )}
                            >
                                <FieldLabel htmlFor="qr-available-from">
                                    Available from
                                </FieldLabel>
                                <Input
                                    id="qr-available-from"
                                    type="datetime-local"
                                    value={form.data.available_from}
                                    onChange={(event) =>
                                        form.setData(
                                            'available_from',
                                            event.target.value,
                                        )
                                    }
                                    disabled={form.processing}
                                />
                                <FieldMessage>
                                    {form.errors.available_from}
                                </FieldMessage>
                            </Field>
                            <Field
                                data-invalid={Boolean(form.errors.expires_at)}
                            >
                                <FieldLabel htmlFor="qr-expires-at">
                                    Expires at
                                </FieldLabel>
                                <Input
                                    id="qr-expires-at"
                                    type="datetime-local"
                                    value={form.data.expires_at}
                                    onChange={(event) =>
                                        form.setData(
                                            'expires_at',
                                            event.target.value,
                                        )
                                    }
                                    disabled={form.processing}
                                />
                                <FieldMessage>
                                    {form.errors.expires_at}
                                </FieldMessage>
                            </Field>
                        </div>

                        <Field data-invalid={Boolean(form.errors.access_code)}>
                            <FieldLabel htmlFor="qr-access-code">
                                {campaign?.has_access_code
                                    ? 'Replace access code'
                                    : 'Access code'}
                            </FieldLabel>
                            <Input
                                id="qr-access-code"
                                type="password"
                                value={form.data.access_code}
                                onChange={(event) =>
                                    form.setData(
                                        'access_code',
                                        event.target.value,
                                    )
                                }
                                autoComplete="new-password"
                                maxLength={255}
                                placeholder={
                                    campaign?.has_access_code
                                        ? 'Leave blank to keep current'
                                        : 'Optional'
                                }
                                disabled={form.processing}
                            />
                            <FieldDescription>
                                Visitors must unlock this QR before a scan is
                                counted.
                            </FieldDescription>
                            {campaign?.has_access_code && (
                                <label className="flex items-center gap-2 text-sm">
                                    <input
                                        type="checkbox"
                                        checked={form.data.remove_access_code}
                                        onChange={(event) =>
                                            form.setData(
                                                'remove_access_code',
                                                event.target.checked,
                                            )
                                        }
                                        disabled={form.processing}
                                    />
                                    Remove the current access code
                                </label>
                            )}
                            <FieldMessage>
                                {form.errors.access_code}
                            </FieldMessage>
                        </Field>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field
                                data-invalid={Boolean(
                                    form.errors.foreground_color,
                                )}
                            >
                                <FieldLabel htmlFor="qr-foreground">
                                    Foreground
                                </FieldLabel>
                                <Input
                                    id="qr-foreground"
                                    type="color"
                                    value={form.data.foreground_color}
                                    onChange={(event) =>
                                        form.setData(
                                            'foreground_color',
                                            event.target.value,
                                        )
                                    }
                                    disabled={form.processing}
                                />
                                <FieldMessage>
                                    {form.errors.foreground_color}
                                </FieldMessage>
                            </Field>
                            <Field
                                data-invalid={Boolean(
                                    form.errors.background_color,
                                )}
                            >
                                <FieldLabel htmlFor="qr-background">
                                    Background
                                </FieldLabel>
                                <Input
                                    id="qr-background"
                                    type="color"
                                    value={form.data.background_color}
                                    onChange={(event) =>
                                        form.setData(
                                            'background_color',
                                            event.target.value,
                                        )
                                    }
                                    disabled={form.processing}
                                />
                                <FieldMessage>
                                    {form.errors.background_color}
                                </FieldMessage>
                            </Field>
                            <Field
                                data-invalid={Boolean(
                                    form.errors.error_correction,
                                )}
                            >
                                <FieldLabel htmlFor="qr-correction">
                                    Error correction
                                </FieldLabel>
                                <Select
                                    value={form.data.error_correction}
                                    onValueChange={(
                                        value: FormData['error_correction'],
                                    ) =>
                                        form.setData('error_correction', value)
                                    }
                                    disabled={form.processing}
                                >
                                    <SelectTrigger id="qr-correction">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="low">Low</SelectItem>
                                        <SelectItem value="medium">
                                            Medium
                                        </SelectItem>
                                        <SelectItem value="quartile">
                                            Quartile
                                        </SelectItem>
                                        <SelectItem value="high">
                                            High
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <FieldMessage>
                                    {form.errors.error_correction}
                                </FieldMessage>
                            </Field>
                            <Field data-invalid={Boolean(form.errors.size)}>
                                <FieldLabel htmlFor="qr-size">
                                    Size (pixels)
                                </FieldLabel>
                                <Input
                                    id="qr-size"
                                    type="number"
                                    min={128}
                                    max={1024}
                                    value={form.data.size}
                                    onChange={(event) =>
                                        form.setData('size', event.target.value)
                                    }
                                    disabled={form.processing}
                                />
                                <FieldMessage>{form.errors.size}</FieldMessage>
                            </Field>
                            <Field data-invalid={Boolean(form.errors.margin)}>
                                <FieldLabel htmlFor="qr-margin">
                                    Quiet-zone margin
                                </FieldLabel>
                                <Input
                                    id="qr-margin"
                                    type="number"
                                    min={0}
                                    max={20}
                                    value={form.data.margin}
                                    onChange={(event) =>
                                        form.setData(
                                            'margin',
                                            event.target.value,
                                        )
                                    }
                                    disabled={form.processing}
                                />
                                <FieldMessage>
                                    {form.errors.margin}
                                </FieldMessage>
                            </Field>
                        </div>

                        <Field
                            data-invalid={Boolean(
                                form.errors.logo || form.errors.logo_alt,
                            )}
                        >
                            <FieldLabel htmlFor="qr-logo">
                                Logo image
                            </FieldLabel>
                            <Input
                                id="qr-logo"
                                type="file"
                                accept="image/png,image/jpeg,image/webp"
                                onChange={(event) =>
                                    form.setData(
                                        'logo',
                                        event.target.files?.[0] ?? null,
                                    )
                                }
                                disabled={form.processing}
                            />
                            <FieldDescription>
                                Optional PNG, JPEG, or WebP image; up to 2 MB
                                and 1024 × 1024 pixels. Stored on the private
                                workspace disk and embedded in downloads.
                            </FieldDescription>
                            {campaign?.has_logo && (
                                <label className="flex items-center gap-2 text-sm">
                                    <input
                                        type="checkbox"
                                        checked={form.data.clear_logo}
                                        onChange={(event) =>
                                            form.setData(
                                                'clear_logo',
                                                event.target.checked,
                                            )
                                        }
                                        disabled={form.processing}
                                    />
                                    Remove current logo
                                </label>
                            )}
                            <FieldLabel htmlFor="qr-logo-alt">
                                Logo description
                            </FieldLabel>
                            <Input
                                id="qr-logo-alt"
                                value={form.data.logo_alt}
                                onChange={(event) =>
                                    form.setData('logo_alt', event.target.value)
                                }
                                maxLength={160}
                                disabled={form.processing}
                            />
                            <FieldMessage>{form.errors.logo}</FieldMessage>
                            <FieldMessage>{form.errors.logo_alt}</FieldMessage>
                        </Field>

                        <label className="flex items-start gap-3 rounded-md border p-3 text-sm">
                            <input
                                type="checkbox"
                                checked={form.data.is_active}
                                onChange={(event) =>
                                    form.setData(
                                        'is_active',
                                        event.target.checked,
                                    )
                                }
                                disabled={form.processing}
                                className="mt-0.5"
                            />
                            <span>
                                <span className="block font-medium">
                                    Active
                                </span>
                                <span className="text-muted-foreground">
                                    Paused QR codes reject scans without
                                    incrementing analytics.
                                </span>
                            </span>
                        </label>
                    </FieldGroup>

                    {planError && (
                        <p role="alert" className="text-sm text-destructive">
                            {planError}
                        </p>
                    )}

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setOpen(false)}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing
                                ? 'Saving…'
                                : campaign
                                  ? 'Save changes'
                                  : 'Create QR campaign'}
                        </Button>
                    </DialogFooter>
                </form>

                {campaign ? (
                    <div className="grid gap-4 rounded-lg border p-4 sm:grid-cols-[9rem_minmax(0,1fr)] sm:items-center">
                        <img
                            src={preview(campaign.id).url}
                            alt={[
                                `QR code preview for ${campaign.title || 'campaign'}`,
                                campaign.logo_alt
                                    ? `Includes logo: ${campaign.logo_alt}`
                                    : null,
                            ]
                                .filter(Boolean)
                                .join('. ')}
                            className="mx-auto aspect-square size-36 rounded-md border"
                        />
                        <p className="text-sm text-muted-foreground">
                            This preview encodes the stable campaign URL /q/
                            {campaign.code}. Download formats are available from
                            the campaign card.
                        </p>
                    </div>
                ) : (
                    <div className="grid gap-4 rounded-lg border p-4 sm:grid-cols-[9rem_minmax(0,1fr)] sm:items-center">
                        <div className="relative mx-auto size-36">
                            <img
                                src={draftPreviewUrl}
                                alt="QR style preview"
                                className="aspect-square size-36 rounded-md border"
                            />
                            {logoPreviewUrl && (
                                <img
                                    src={logoPreviewUrl}
                                    alt={form.data.logo_alt || 'Selected logo'}
                                    className="absolute top-1/2 left-1/2 aspect-square -translate-x-1/2 -translate-y-1/2 rounded bg-white object-contain p-1"
                                    style={{
                                        width: `${(Math.min(128, Math.floor(previewSize / 4)) / previewSize) * 100}%`,
                                    }}
                                />
                            )}
                        </div>
                        <p className="text-sm text-muted-foreground">
                            This draft image encodes a non-live preview URL; it
                            cannot open a campaign. Save first, then use the
                            campaign preview or a download to get a scan-ready
                            QR.
                        </p>
                    </div>
                )}
            </DialogContent>
        </Dialog>
    );
}
