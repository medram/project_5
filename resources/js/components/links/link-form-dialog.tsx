import { useForm } from '@inertiajs/react';
import { type FormEvent, type ReactNode, useState } from 'react';
import { Button } from '@/components/ui/button';
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
import { store, update } from '@/routes/links';
import type { CustomDomainOption } from '@/types/custom-domains';
import type { FolderOption } from '@/types/folders';
import type { LinkListItem } from '@/types/links';
import type { LinkOverlayOption } from '@/types/overlays';

type LinkFormData = {
    destination_url: string;
    title: string;
    slug: string;
    is_active: boolean;
    folder_id: string;
    custom_domain_id: string;
    link_overlay_id: string;
    available_from: string;
    expires_at: string;
    access_code: string;
    clear_access_code: boolean;
};

type LinkFormDialogProps = {
    link?: LinkListItem;
    folders: FolderOption[];
    customDomains: CustomDomainOption[];
    linkOverlays: LinkOverlayOption[];
    children: ReactNode;
};

function dateTimeLocalValue(value: string | null | undefined): string {
    if (!value) {
        return '';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '';
    }

    return new Date(date.getTime() - date.getTimezoneOffset() * 60_000)
        .toISOString()
        .slice(0, 16);
}

function dateTimeUtcValue(value: string): string {
    if (value === '') {
        return '';
    }

    const date = new Date(value);

    return Number.isNaN(date.getTime()) ? value : date.toISOString();
}

function formDataFor(link?: LinkListItem): LinkFormData {
    return {
        destination_url: link?.destinationUrl ?? '',
        title: link?.title ?? '',
        slug: link?.slug ?? '',
        is_active: link?.isActive ?? true,
        folder_id: link?.folderId?.toString() ?? '',
        custom_domain_id: link?.customDomainId?.toString() ?? '',
        link_overlay_id: link?.linkOverlayId?.toString() ?? '',
        available_from: dateTimeLocalValue(link?.availableFrom),
        expires_at: dateTimeLocalValue(link?.expiresAt),
        access_code: '',
        clear_access_code: false,
    };
}

export function LinkFormDialog({
    link,
    folders,
    customDomains,
    linkOverlays,
    children,
}: LinkFormDialogProps) {
    const [open, setOpen] = useState(false);
    const [accessCodeEnabled, setAccessCodeEnabled] = useState(
        link?.hasAccessCode ?? false,
    );
    const form = useForm<LinkFormData>(formDataFor(link));
    const isEditing = link !== undefined;

    function handleOpenChange(nextOpen: boolean) {
        if (form.processing) {
            return;
        }

        setOpen(nextOpen);

        if (nextOpen) {
            form.clearErrors();
            form.setData(formDataFor(link));
            setAccessCodeEnabled(link?.hasAccessCode ?? false);
        }
    }

    function toggleAccessCode(enabled: boolean) {
        setAccessCodeEnabled(enabled);
        form.setData({
            ...form.data,
            access_code: enabled ? form.data.access_code : '',
            clear_access_code: !enabled && (link?.hasAccessCode ?? false),
        });
    }

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        form.transform((data) => ({
            ...data,
            available_from: dateTimeUtcValue(data.available_from),
            expires_at: dateTimeUtcValue(data.expires_at),
        }));
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
                form.reset();
            },
        };

        if (link) {
            form.patch(update(link.id).url, options);

            return;
        }

        form.post(store().url, options);
    }

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogTrigger asChild>{children}</DialogTrigger>
            <DialogContent className="sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>
                        {isEditing ? 'Edit short link' : 'Create short link'}
                    </DialogTitle>
                    <DialogDescription>
                        {isEditing
                            ? 'Update where this link sends visitors.'
                            : 'Add a destination and publish a route when you are ready.'}
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={submit}>
                    <FieldGroup>
                        <Field
                            data-invalid={Boolean(form.errors.destination_url)}
                        >
                            <FieldLabel htmlFor="destination-url">
                                Destination URL
                            </FieldLabel>
                            <Input
                                id="destination-url"
                                type="url"
                                value={form.data.destination_url}
                                onChange={(event) =>
                                    form.setData(
                                        'destination_url',
                                        event.target.value,
                                    )
                                }
                                placeholder="https://example.com/launch"
                                maxLength={2048}
                                autoComplete="url"
                                autoFocus
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

                        <Field data-invalid={Boolean(form.errors.title)}>
                            <FieldLabel htmlFor="link-title">Title</FieldLabel>
                            <Input
                                id="link-title"
                                value={form.data.title}
                                onChange={(event) =>
                                    form.setData('title', event.target.value)
                                }
                                placeholder="Spring campaign"
                                maxLength={160}
                                autoComplete="off"
                                disabled={form.processing}
                                aria-invalid={Boolean(form.errors.title)}
                            />
                            {form.errors.title && (
                                <FieldError>{form.errors.title}</FieldError>
                            )}
                        </Field>

                        <Field data-invalid={Boolean(form.errors.slug)}>
                            <FieldLabel htmlFor="custom-slug">
                                Custom slug
                            </FieldLabel>
                            <Input
                                id="custom-slug"
                                value={form.data.slug}
                                onChange={(event) =>
                                    form.setData('slug', event.target.value)
                                }
                                placeholder="spring-launch"
                                maxLength={32}
                                autoComplete="off"
                                required={isEditing}
                                disabled={form.processing}
                                aria-invalid={Boolean(form.errors.slug)}
                            />
                            <FieldDescription>
                                {isEditing
                                    ? 'Required for existing links. Use 4–32 lowercase letters, numbers, or hyphens.'
                                    : 'Optional. Use 4–32 lowercase letters, numbers, or hyphens.'}
                            </FieldDescription>
                            {form.errors.slug && (
                                <FieldError>{form.errors.slug}</FieldError>
                            )}
                        </Field>

                        <Field data-invalid={Boolean(form.errors.folder_id)}>
                            <FieldLabel htmlFor="link-folder">
                                Folder
                            </FieldLabel>
                            <Select
                                value={form.data.folder_id || 'none'}
                                onValueChange={(value) =>
                                    form.setData(
                                        'folder_id',
                                        value === 'none' ? '' : value,
                                    )
                                }
                                disabled={form.processing}
                            >
                                <SelectTrigger id="link-folder">
                                    <SelectValue placeholder="No folder" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none">
                                        No folder
                                    </SelectItem>
                                    {folders.map((folder) => (
                                        <SelectItem
                                            key={folder.id}
                                            value={folder.id.toString()}
                                        >
                                            {folder.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {form.errors.folder_id && (
                                <FieldError>{form.errors.folder_id}</FieldError>
                            )}
                        </Field>

                        <Field
                            data-invalid={Boolean(form.errors.custom_domain_id)}
                        >
                            <FieldLabel htmlFor="link-custom-domain">
                                Short-link host
                            </FieldLabel>
                            <Select
                                value={form.data.custom_domain_id || 'default'}
                                onValueChange={(value) =>
                                    form.setData(
                                        'custom_domain_id',
                                        value === 'default' ? '' : value,
                                    )
                                }
                                disabled={form.processing}
                            >
                                <SelectTrigger id="link-custom-domain">
                                    <SelectValue placeholder="LinkPilot default host" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="default">
                                        LinkPilot default host
                                    </SelectItem>
                                    {customDomains.map((domain) => (
                                        <SelectItem
                                            key={domain.id}
                                            value={domain.id.toString()}
                                        >
                                            {domain.hostname}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <FieldDescription>
                                Only active, verified domains are available.
                            </FieldDescription>
                            {form.errors.custom_domain_id && (
                                <FieldError>
                                    {form.errors.custom_domain_id}
                                </FieldError>
                            )}
                        </Field>

                        <Field data-invalid={Boolean(form.errors.link_overlay_id)}>
                            <FieldLabel htmlFor="link-overlay">
                                CTA overlay
                            </FieldLabel>
                            <Select
                                value={form.data.link_overlay_id || 'none'}
                                onValueChange={(value) =>
                                    form.setData(
                                        'link_overlay_id',
                                        value === 'none' ? '' : value,
                                    )
                                }
                                disabled={form.processing}
                            >
                                <SelectTrigger id="link-overlay">
                                    <SelectValue placeholder="No overlay" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none">
                                        No overlay
                                    </SelectItem>
                                    {linkOverlays.map((overlay) => (
                                        <SelectItem
                                            key={overlay.id}
                                            value={overlay.id.toString()}
                                        >
                                            {overlay.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <FieldDescription>
                                An active overlay shows a branded interstitial
                                before delivering the destination.
                            </FieldDescription>
                            {form.errors.link_overlay_id && (
                                <FieldError>
                                    {form.errors.link_overlay_id}
                                </FieldError>
                            )}
                        </Field>

                        <div className="grid gap-5 sm:grid-cols-2">
                            <Field
                                data-invalid={Boolean(
                                    form.errors.available_from,
                                )}
                            >
                                <FieldLabel htmlFor="available-from">
                                    Available from
                                </FieldLabel>
                                <Input
                                    id="available-from"
                                    type="datetime-local"
                                    value={form.data.available_from}
                                    onChange={(event) =>
                                        form.setData(
                                            'available_from',
                                            event.target.value,
                                        )
                                    }
                                    disabled={form.processing}
                                    aria-invalid={Boolean(
                                        form.errors.available_from,
                                    )}
                                />
                                <FieldDescription>
                                    Leave empty to publish immediately.
                                </FieldDescription>
                                {form.errors.available_from && (
                                    <FieldError>
                                        {form.errors.available_from}
                                    </FieldError>
                                )}
                            </Field>

                            <Field
                                data-invalid={Boolean(form.errors.expires_at)}
                            >
                                <FieldLabel htmlFor="expires-at">
                                    Expires at
                                </FieldLabel>
                                <Input
                                    id="expires-at"
                                    type="datetime-local"
                                    value={form.data.expires_at}
                                    onChange={(event) =>
                                        form.setData(
                                            'expires_at',
                                            event.target.value,
                                        )
                                    }
                                    disabled={form.processing}
                                    aria-invalid={Boolean(
                                        form.errors.expires_at,
                                    )}
                                />
                                <FieldDescription>
                                    Leave empty to keep the link live.
                                </FieldDescription>
                                {form.errors.expires_at && (
                                    <FieldError>
                                        {form.errors.expires_at}
                                    </FieldError>
                                )}
                            </Field>
                        </div>

                        <Field data-invalid={Boolean(form.errors.access_code)}>
                            <div className="flex items-start gap-3">
                                <Checkbox
                                    id="require-access-code"
                                    checked={accessCodeEnabled}
                                    onCheckedChange={(checked) =>
                                        toggleAccessCode(checked === true)
                                    }
                                    disabled={form.processing}
                                />
                                <div className="flex min-w-0 flex-1 flex-col gap-1">
                                    <FieldLabel htmlFor="require-access-code">
                                        Require an access code
                                    </FieldLabel>
                                    <FieldDescription>
                                        Visitors must enter a code before this
                                        route redirects.
                                    </FieldDescription>
                                </div>
                            </div>

                            {accessCodeEnabled && (
                                <div className="flex flex-col gap-3">
                                    <Input
                                        id="access-code"
                                        type="password"
                                        value={form.data.access_code}
                                        onChange={(event) =>
                                            form.setData(
                                                'access_code',
                                                event.target.value,
                                            )
                                        }
                                        placeholder={
                                            link?.hasAccessCode
                                                ? 'Enter a new code to replace the current one'
                                                : 'Set an access code'
                                        }
                                        autoComplete="new-password"
                                        required={!link?.hasAccessCode}
                                        disabled={form.processing}
                                        aria-invalid={Boolean(
                                            form.errors.access_code,
                                        )}
                                    />
                                    {link?.hasAccessCode && (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            className="w-fit"
                                            onClick={() =>
                                                toggleAccessCode(false)
                                            }
                                            disabled={form.processing}
                                        >
                                            Remove access code
                                        </Button>
                                    )}
                                </div>
                            )}

                            {form.errors.access_code && (
                                <FieldError>
                                    {form.errors.access_code}
                                </FieldError>
                            )}
                        </Field>

                        <DialogFooter>
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
                                {isEditing ? 'Save changes' : 'Create link'}
                            </Button>
                        </DialogFooter>
                    </FieldGroup>
                </form>
            </DialogContent>
        </Dialog>
    );
}
