import { useForm } from '@inertiajs/react';
import { type FormEvent, type ReactNode, useRef, useState } from 'react';
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
import type {
    LinkDeliveryMode,
    LinkListItem,
    LinkPageOption,
    LinkTagOption,
} from '@/types/links';
import type { LinkOverlayOption } from '@/types/overlays';

type RoutingRuleInput = {
    rule_type: 'country' | 'device' | 'platform';
    match_value: string;
    target_url: string;
};

type CustomParameterInput = {
    key: string;
    value: string;
};

type LinkFormData = {
    destination_url: string;
    delivery_mode: LinkDeliveryMode;
    link_page_id: string;
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
    tag_ids: number[];
    max_clicks: string;
    approval_required: boolean;
    utm_parameters: Record<string, string>;
    custom_parameters: CustomParameterInput[];
    routing_rules: RoutingRuleInput[];
};

type LinkFormDialogProps = {
    link?: LinkListItem;
    folders: FolderOption[];
    customDomains: CustomDomainOption[];
    linkOverlays: LinkOverlayOption[];
    linkPages: LinkPageOption[];
    tagOptions: LinkTagOption[];
    canManageApprovals: boolean;
    listQuery: string;
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
        delivery_mode: link?.deliveryMode ?? 'direct',
        link_page_id: link?.linkPageId?.toString() ?? '',
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
        tag_ids: link?.tags.map((tag) => tag.id) ?? [],
        max_clicks: link?.maxClicks?.toString() ?? '',
        approval_required: link?.approvalRequired ?? false,
        utm_parameters: link?.utmParameters ?? {},
        custom_parameters: Object.entries(link?.customParameters ?? {}).map(
            ([key, value]) => ({ key, value }),
        ),
        routing_rules: (link?.routingRules ?? []).map((rule) => ({
            rule_type: rule.rule_type,
            match_value: rule.match_value,
            target_url: rule.target_url,
        })),
    };
}

function withListQuery(url: string, listQuery: string): string {
    return listQuery === '' ? url : `${url}?${listQuery}`;
}
export function LinkFormDialog({
    link,
    folders,
    customDomains,
    linkOverlays,
    linkPages,
    canManageApprovals,
    listQuery,
    children,
    tagOptions,
}: LinkFormDialogProps) {
    const [open, setOpen] = useState(false);
    const [accessCodeEnabled, setAccessCodeEnabled] = useState(
        link?.hasAccessCode ?? false,
    );
    const form = useForm<LinkFormData>(formDataFor(link));
    const isEditing = link !== undefined;
    const ruleKeyCounter = useRef(0);
    const [routingRuleKeys, setRoutingRuleKeys] = useState<number[]>([]);

    function handleOpenChange(nextOpen: boolean) {
        if (form.processing) {
            return;
        }

        setOpen(nextOpen);

        if (nextOpen) {
            form.clearErrors();
            const data = formDataFor(link);
            form.setData(data);
            setRoutingRuleKeys(
                data.routing_rules.map(() => ruleKeyCounter.current++),
            );
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

    function changeDeliveryMode(deliveryMode: LinkDeliveryMode) {
        form.setData({
            ...form.data,
            delivery_mode: deliveryMode,
        });
    }

    function updateRoutingRule(
        index: number,
        changes: Partial<RoutingRuleInput>,
    ) {
        form.setData(
            'routing_rules',
            form.data.routing_rules.map((rule, ruleIndex) =>
                ruleIndex === index ? { ...rule, ...changes } : rule,
            ),
        );
    }
    function moveRoutingRule(index: number, offset: -1 | 1) {
        const nextIndex = index + offset;

        if (nextIndex < 0 || nextIndex >= form.data.routing_rules.length) {
            return;
        }

        const rules = [...form.data.routing_rules];
        [rules[index], rules[nextIndex]] = [rules[nextIndex], rules[index]];
        form.setData('routing_rules', rules);
        setRoutingRuleKeys((keys) => {
            const nextKeys = [...keys];
            [nextKeys[index], nextKeys[nextIndex]] = [
                nextKeys[nextIndex],
                nextKeys[index],
            ];
            return nextKeys;
        });
        form.clearErrors();
    }

    function updateCustomParameter(
        index: number,
        changes: Partial<CustomParameterInput>,
    ) {
        form.setData(
            'custom_parameters',
            form.data.custom_parameters.map((parameter, parameterIndex) =>
                parameterIndex === index
                    ? { ...parameter, ...changes }
                    : parameter,
            ),
        );
    }

    function previewDestination(): string {
        if (!form.data.destination_url) {
            return '';
        }

        try {
            const preview = new URL(form.data.destination_url);
            const parameters = new Map<string, string>(
                Object.entries(form.data.utm_parameters).filter(
                    ([, value]) => value.trim() !== '',
                ),
            );
            for (const { key, value } of form.data.custom_parameters) {
                const trimmedKey = key.trim();
                if (trimmedKey !== '' && value !== '') {
                    parameters.set(trimmedKey, value);
                }
            }

            for (const key of parameters.keys()) {
                preview.searchParams.delete(key);
            }
            for (const [key, value] of parameters) {
                preview.searchParams.append(key, value);
            }

            return preview.toString();
        } catch {
            return '';
        }
    }

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        form.transform((data) => ({
            ...data,
            destination_url:
                data.delivery_mode === 'link_page' ? '' : data.destination_url,
            link_overlay_id:
                data.delivery_mode === 'overlay' ? data.link_overlay_id : '',
            link_page_id:
                data.delivery_mode === 'link_page' ? data.link_page_id : '',
            available_from: dateTimeUtcValue(data.available_from),
            expires_at: dateTimeUtcValue(data.expires_at),
            max_clicks: data.max_clicks === '' ? null : Number(data.max_clicks),
            utm_parameters: Object.fromEntries(
                Object.entries(data.utm_parameters).filter(
                    ([, value]) => value.trim() !== '',
                ),
            ),
            custom_parameters: Object.fromEntries(
                data.custom_parameters
                    .filter(
                        ({ key, value }) => key.trim() !== '' && value !== '',
                    )
                    .map(({ key, value }) => [key.trim(), value]),
            ),
        }));
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
                form.reset();
            },
        };

        if (link) {
            form.patch(withListQuery(update(link.id).url, listQuery), options);

            return;
        }

        form.post(withListQuery(store().url, listQuery), options);
    }

    const planError = (form.errors as Record<string, string | undefined>).plan;
    const preview = previewDestination();

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogTrigger asChild>{children}</DialogTrigger>
            <DialogContent
                className="flex max-h-[calc(100dvh-2rem)] flex-col overflow-hidden sm:max-w-xl"
                onOpenAutoFocus={(event) => {
                    if (window.matchMedia('(max-width: 640px)').matches) {
                        event.preventDefault();
                        document
                            .getElementById('link-form-dialog-title')
                            ?.focus();
                    }
                }}
            >
                <DialogHeader className="shrink-0">
                    <DialogTitle id="link-form-dialog-title" tabIndex={-1}>
                        {isEditing ? 'Edit short link' : 'Create short link'}
                    </DialogTitle>
                    <DialogDescription>
                        {isEditing
                            ? 'Update where this link sends visitors.'
                            : 'Add a destination and publish a route when you are ready.'}
                    </DialogDescription>
                </DialogHeader>
                {planError && (
                    <p className="text-sm text-destructive">{planError}</p>
                )}

                <form
                    onSubmit={submit}
                    className="min-h-0 flex-1 overflow-y-auto pr-2"
                >
                    <FieldGroup>
                        <Field
                            data-invalid={Boolean(form.errors.delivery_mode)}
                        >
                            <FieldLabel htmlFor="link-delivery-mode">
                                Delivery mode
                            </FieldLabel>
                            <Select
                                value={form.data.delivery_mode}
                                onValueChange={(value) =>
                                    changeDeliveryMode(
                                        value as LinkDeliveryMode,
                                    )
                                }
                                disabled={form.processing}
                            >
                                <SelectTrigger id="link-delivery-mode">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="direct">
                                        Direct redirect
                                    </SelectItem>
                                    <SelectItem value="splash">
                                        LinkPilot splash
                                    </SelectItem>
                                    <SelectItem value="link_page">
                                        Link page
                                    </SelectItem>
                                    <SelectItem value="overlay">
                                        CTA overlay
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <FieldDescription>
                                Only the selected delivery mode’s target is
                                saved. Other destination, page, and overlay
                                values are not saved.
                            </FieldDescription>
                            {form.errors.delivery_mode && (
                                <FieldError>
                                    {form.errors.delivery_mode}
                                </FieldError>
                            )}
                        </Field>
                        {form.data.delivery_mode !== 'link_page' && (
                            <Field
                                data-invalid={Boolean(
                                    form.errors.destination_url,
                                )}
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
                                    disabled={form.processing}
                                    required
                                    aria-invalid={Boolean(
                                        form.errors.destination_url,
                                    )}
                                />
                                {form.errors.destination_url && (
                                    <FieldError>
                                        {form.errors.destination_url}
                                    </FieldError>
                                )}
                                {preview && (
                                    <FieldDescription className="break-all">
                                        Non-executing redirect preview:{' '}
                                        <code>{preview}</code>
                                    </FieldDescription>
                                )}
                            </Field>
                        )}

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
                        <fieldset
                            disabled={form.processing}
                            className="flex flex-col gap-2"
                        >
                            <legend className="text-sm font-medium">
                                Tags
                            </legend>
                            {tagOptions.length === 0 ? (
                                <FieldDescription>
                                    Create a tag from the link list to organize
                                    this link.
                                </FieldDescription>
                            ) : (
                                <div className="flex flex-wrap gap-3">
                                    {tagOptions.map((tag) => {
                                        const checked =
                                            form.data.tag_ids.includes(tag.id);
                                        const disabled =
                                            !checked &&
                                            form.data.tag_ids.length >= 10;

                                        return (
                                            <label
                                                key={tag.id}
                                                className="inline-flex min-h-11 items-center gap-2 text-sm"
                                            >
                                                <Checkbox
                                                    checked={checked}
                                                    disabled={disabled}
                                                    onCheckedChange={(value) =>
                                                        form.setData(
                                                            'tag_ids',
                                                            value === true
                                                                ? [
                                                                      ...form
                                                                          .data
                                                                          .tag_ids,
                                                                      tag.id,
                                                                  ]
                                                                : form.data.tag_ids.filter(
                                                                      (id) =>
                                                                          id !==
                                                                          tag.id,
                                                                  ),
                                                        )
                                                    }
                                                />
                                                <span>{tag.name}</span>
                                            </label>
                                        );
                                    })}
                                </div>
                            )}
                            <FieldDescription>
                                Select up to 10 tags.
                            </FieldDescription>
                            {form.errors['tag_ids.0'] && (
                                <FieldError>
                                    {form.errors['tag_ids.0']}
                                </FieldError>
                            )}
                        </fieldset>

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

                        {form.data.delivery_mode === 'overlay' && (
                            <Field
                                data-invalid={Boolean(
                                    form.errors.link_overlay_id,
                                )}
                            >
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
                                        <SelectValue placeholder="Choose an overlay" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="none">
                                            Choose an overlay
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
                                    An active overlay shows a branded
                                    interstitial before delivering the
                                    destination.
                                </FieldDescription>
                                {form.errors.link_overlay_id && (
                                    <FieldError>
                                        {form.errors.link_overlay_id}
                                    </FieldError>
                                )}
                            </Field>
                        )}

                        {form.data.delivery_mode === 'link_page' && (
                            <Field
                                data-invalid={Boolean(form.errors.link_page_id)}
                            >
                                <FieldLabel htmlFor="link-page">
                                    Published link page
                                </FieldLabel>
                                <Select
                                    value={form.data.link_page_id || 'none'}
                                    onValueChange={(value) =>
                                        form.setData(
                                            'link_page_id',
                                            value === 'none' ? '' : value,
                                        )
                                    }
                                    disabled={form.processing}
                                >
                                    <SelectTrigger id="link-page">
                                        <SelectValue placeholder="Choose a page" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="none">
                                            Choose a page
                                        </SelectItem>
                                        {linkPages.map((page) => (
                                            <SelectItem
                                                key={page.id}
                                                value={page.id.toString()}
                                            >
                                                {page.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <FieldDescription>
                                    Link-page delivery records the visit before
                                    sending visitors to this published page.
                                </FieldDescription>
                                {form.errors.link_page_id && (
                                    <FieldError>
                                        {form.errors.link_page_id}
                                    </FieldError>
                                )}
                            </Field>
                        )}

                        <fieldset className="flex flex-col gap-3">
                            <legend className="text-sm font-medium">
                                UTM parameters
                            </legend>
                            <FieldDescription>
                                Configured values replace existing occurrences
                                of the same key; unrelated query values and the
                                URL fragment are preserved.
                            </FieldDescription>
                            <div className="grid gap-4 sm:grid-cols-2">
                                {[
                                    ['utm_source', 'Source'],
                                    ['utm_medium', 'Medium'],
                                    ['utm_campaign', 'Campaign'],
                                    ['utm_term', 'Term'],
                                    ['utm_content', 'Content'],
                                ].map(([key, label]) => (
                                    <Field key={key}>
                                        <FieldLabel htmlFor={`utm-${key}`}>
                                            {label}
                                        </FieldLabel>
                                        <Input
                                            id={`utm-${key}`}
                                            value={
                                                form.data.utm_parameters[key] ??
                                                ''
                                            }
                                            maxLength={512}
                                            disabled={form.processing}
                                            onChange={(event) =>
                                                form.setData('utm_parameters', {
                                                    ...form.data.utm_parameters,
                                                    [key]: event.target.value,
                                                })
                                            }
                                        />
                                    </Field>
                                ))}
                            </div>
                        </fieldset>

                        <fieldset className="flex flex-col gap-3">
                            <legend className="text-sm font-medium">
                                Custom query parameters
                            </legend>
                            <FieldDescription>
                                Add up to 10 key/value pairs. Preview is local;
                                the destination is never loaded here.
                            </FieldDescription>
                            {form.data.custom_parameters.map(
                                (parameter, parameterIndex) => (
                                    <div
                                        key={parameterIndex}
                                        className="grid gap-2 sm:grid-cols-[1fr_1fr_auto]"
                                    >
                                        <Input
                                            aria-label={`Custom parameter ${parameterIndex + 1} name`}
                                            value={parameter.key}
                                            placeholder="ref"
                                            maxLength={64}
                                            disabled={form.processing}
                                            onChange={(event) =>
                                                updateCustomParameter(
                                                    parameterIndex,
                                                    { key: event.target.value },
                                                )
                                            }
                                        />
                                        <Input
                                            aria-label={`Custom parameter ${parameterIndex + 1} value`}
                                            value={parameter.value}
                                            placeholder="newsletter"
                                            maxLength={512}
                                            disabled={form.processing}
                                            onChange={(event) =>
                                                updateCustomParameter(
                                                    parameterIndex,
                                                    {
                                                        value: event.target
                                                            .value,
                                                    },
                                                )
                                            }
                                        />
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() =>
                                                form.setData(
                                                    'custom_parameters',
                                                    form.data.custom_parameters.filter(
                                                        (_, index) =>
                                                            index !==
                                                            parameterIndex,
                                                    ),
                                                )
                                            }
                                            disabled={form.processing}
                                        >
                                            Remove
                                        </Button>
                                    </div>
                                ),
                            )}
                            <Button
                                type="button"
                                variant="outline"
                                className="w-fit"
                                onClick={() =>
                                    form.setData('custom_parameters', [
                                        ...form.data.custom_parameters,
                                        { key: '', value: '' },
                                    ])
                                }
                                disabled={
                                    form.processing ||
                                    form.data.custom_parameters.length >= 10
                                }
                            >
                                Add parameter
                            </Button>
                            {form.errors.custom_parameters && (
                                <FieldError>
                                    {form.errors.custom_parameters}
                                </FieldError>
                            )}
                        </fieldset>

                        <fieldset className="flex flex-col gap-3">
                            <legend className="text-sm font-medium">
                                Ordered targeting rules
                            </legend>
                            <FieldDescription>
                                First matching rule wins. Country matches are
                                disabled unless the socket peer is an explicitly
                                trusted country proxy.
                            </FieldDescription>
                            {form.data.routing_rules.map((rule, ruleIndex) => (
                                <div
                                    key={
                                        routingRuleKeys[ruleIndex] ?? ruleIndex
                                    }
                                    className="flex flex-col gap-2 rounded-md border p-3"
                                >
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <span className="text-sm font-medium">
                                            Rule {ruleIndex + 1} · Priority{' '}
                                            {ruleIndex + 1}
                                        </span>
                                        <div className="flex flex-wrap gap-2">
                                            <Button
                                                type="button"
                                                variant="outline"
                                                className="min-h-11"
                                                aria-label={`Move targeting rule ${ruleIndex + 1} up`}
                                                onClick={() =>
                                                    moveRoutingRule(
                                                        ruleIndex,
                                                        -1,
                                                    )
                                                }
                                                disabled={
                                                    form.processing ||
                                                    ruleIndex === 0
                                                }
                                            >
                                                Move up
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                className="min-h-11"
                                                aria-label={`Move targeting rule ${ruleIndex + 1} down`}
                                                onClick={() =>
                                                    moveRoutingRule(
                                                        ruleIndex,
                                                        1,
                                                    )
                                                }
                                                disabled={
                                                    form.processing ||
                                                    ruleIndex ===
                                                        form.data.routing_rules
                                                            .length -
                                                            1
                                                }
                                            >
                                                Move down
                                            </Button>
                                        </div>
                                    </div>
                                    <div className="grid gap-2 sm:grid-cols-2">
                                        <Select
                                            value={rule.rule_type}
                                            onValueChange={(value) =>
                                                updateRoutingRule(ruleIndex, {
                                                    rule_type:
                                                        value as RoutingRuleInput['rule_type'],
                                                })
                                            }
                                            disabled={form.processing}
                                        >
                                            <SelectTrigger
                                                id={`routing-type-${ruleIndex}`}
                                                aria-label={`Rule ${ruleIndex + 1} type`}
                                            >
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="country">
                                                    Country
                                                </SelectItem>
                                                <SelectItem value="device">
                                                    Device
                                                </SelectItem>
                                                <SelectItem value="platform">
                                                    Platform
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <Input
                                            id={`routing-match-${ruleIndex}`}
                                            aria-label={`Rule ${ruleIndex + 1} match value`}
                                            value={rule.match_value}
                                            placeholder={
                                                rule.rule_type === 'country'
                                                    ? 'US'
                                                    : rule.rule_type ===
                                                        'device'
                                                      ? 'mobile'
                                                      : 'android'
                                            }
                                            maxLength={16}
                                            disabled={form.processing}
                                            onChange={(event) =>
                                                updateRoutingRule(ruleIndex, {
                                                    match_value:
                                                        event.target.value,
                                                })
                                            }
                                        />
                                    </div>
                                    <Input
                                        type="url"
                                        aria-label={`Rule ${ruleIndex + 1} target URL`}
                                        value={rule.target_url}
                                        placeholder="https://example.com/mobile"
                                        maxLength={2048}
                                        disabled={form.processing}
                                        onChange={(event) =>
                                            updateRoutingRule(ruleIndex, {
                                                target_url: event.target.value,
                                            })
                                        }
                                    />
                                    {form.errors[
                                        `routing_rules.${ruleIndex}.match_value`
                                    ] && (
                                        <FieldError>
                                            {
                                                form.errors[
                                                    `routing_rules.${ruleIndex}.match_value`
                                                ]
                                            }
                                        </FieldError>
                                    )}
                                    {form.errors[
                                        `routing_rules.${ruleIndex}.target_url`
                                    ] && (
                                        <FieldError>
                                            {
                                                form.errors[
                                                    `routing_rules.${ruleIndex}.target_url`
                                                ]
                                            }
                                        </FieldError>
                                    )}
                                    <Button
                                        type="button"
                                        variant="outline"
                                        className="w-fit"
                                        onClick={() => {
                                            form.setData(
                                                'routing_rules',
                                                form.data.routing_rules.filter(
                                                    (_, index) =>
                                                        index !== ruleIndex,
                                                ),
                                            );
                                            setRoutingRuleKeys((keys) =>
                                                keys.filter(
                                                    (_, index) =>
                                                        index !== ruleIndex,
                                                ),
                                            );
                                        }}
                                        disabled={form.processing}
                                    >
                                        Remove rule
                                    </Button>
                                </div>
                            ))}
                            <Button
                                type="button"
                                variant="outline"
                                className="w-fit"
                                onClick={() => {
                                    form.setData('routing_rules', [
                                        ...form.data.routing_rules,
                                        {
                                            rule_type: 'country',
                                            match_value: '',
                                            target_url: '',
                                        },
                                    ]);
                                    setRoutingRuleKeys((keys) => [
                                        ...keys,
                                        ruleKeyCounter.current++,
                                    ]);
                                }}
                                disabled={
                                    form.processing ||
                                    form.data.routing_rules.length >= 25
                                }
                            >
                                Add targeting rule
                            </Button>
                            {form.errors.routing_rules && (
                                <FieldError>
                                    {form.errors.routing_rules}
                                </FieldError>
                            )}
                        </fieldset>
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
                        <Field data-invalid={Boolean(form.errors.max_clicks)}>
                            <FieldLabel htmlFor="maximum-clicks">
                                Maximum clicks
                            </FieldLabel>
                            <Input
                                id="maximum-clicks"
                                type="number"
                                min={1}
                                max={1000000000}
                                step={1}
                                value={form.data.max_clicks}
                                onChange={(event) =>
                                    form.setData(
                                        'max_clicks',
                                        event.target.value,
                                    )
                                }
                                disabled={form.processing}
                                placeholder="Unlimited"
                            />
                            <FieldDescription>
                                The last permitted delivery succeeds; later
                                requests are blocked atomically.
                            </FieldDescription>
                            {form.errors.max_clicks && (
                                <FieldError>
                                    {form.errors.max_clicks}
                                </FieldError>
                            )}
                        </Field>

                        {canManageApprovals && (
                            <Field>
                                <div className="flex items-start gap-3">
                                    <Checkbox
                                        id="approval-required"
                                        checked={form.data.approval_required}
                                        onCheckedChange={(checked) =>
                                            form.setData(
                                                'approval_required',
                                                checked === true,
                                            )
                                        }
                                        disabled={form.processing}
                                    />
                                    <div className="flex min-w-0 flex-1 flex-col gap-1">
                                        <FieldLabel htmlFor="approval-required">
                                            Require owner/admin review
                                        </FieldLabel>
                                        <FieldDescription>
                                            Proposed edits stay out of the live
                                            route until approved.
                                        </FieldDescription>
                                    </div>
                                </div>
                            </Field>
                        )}

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
