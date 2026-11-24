import { useForm, usePage } from '@inertiajs/react';
import {
    CalendarClock,
    ChartNoAxesCombined,
    ChevronsUpDown,
    Crosshair,
    Link2,
    LockKeyhole,
    type LucideIcon,
} from 'lucide-react';
import { type FormEvent, type ReactNode, useRef, useState } from 'react';
import { useContentEditorRoutes } from '@/components/admin/content-editor-routes';
import { FormErrorSummary } from '@/components/forms/form-error-summary';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';
import { store, update } from '@/routes/links';
import type { CustomDomainOption } from '@/types/custom-domains';
import type { FolderOption } from '@/types/folders';
import type {
    CountryOption,
    LinkDeliveryMode,
    LinkListItem,
    LinkPageOption,
    LinkTagOption,
} from '@/types/links';
import type { LinkOverlayOption } from '@/types/overlays';
import {
    type LinkFormData,
    dateTimeUtcValue,
    formDataFor,
    previewDestination,
    withListQuery,
} from './link-form-data';
import {
    LinkFormSettingsDialogs,
    type LinkSettingsPanel,
} from './link-form-settings-dialogs';

type LinkFormDialogProps = {
    link?: LinkListItem;
    folders: FolderOption[];
    customDomains: CustomDomainOption[];
    linkOverlays: LinkOverlayOption[];
    linkPages: LinkPageOption[];
    tagOptions: LinkTagOption[];
    countries: CountryOption[];
    canManageApprovals: boolean;
    listQuery: string;
    children?: ReactNode;
    open?: boolean;
    onOpenChange?: (open: boolean) => void;
};

type ErrorTarget = {
    panel?: LinkSettingsPanel;
    id?: string;
};

function errorTarget(key: string): ErrorTarget {
    if (key === 'destination_url') return { id: 'destination-url' };
    if (key === 'title') return { id: 'link-title' };
    if (key === 'slug') return { id: 'custom-slug' };
    if (key === 'folder_id') return { id: 'link-folder' };
    if (key === 'custom_domain_id') return { id: 'link-custom-domain' };
    if (key === 'delivery_mode') return { id: 'link-delivery-mode' };
    if (key === 'link_page_id') return { id: 'link-page' };
    if (key === 'link_overlay_id') return { id: 'link-overlay' };
    if (key === 'tag_ids' || key === 'tag_ids.0') {
        return { id: 'link-tags' };
    }
    if (key.startsWith('utm_parameters.')) {
        return { panel: 'tracking', id: `utm-${key.split('.')[1]}` };
    }
    if (key.startsWith('custom_parameters.')) {
        const index = key.split('.')[1] ?? '0';
        return {
            panel: 'tracking',
            id: `custom-parameter-${index}-key`,
        };
    }
    if (key.startsWith('custom_parameters')) {
        return { panel: 'tracking', id: 'utm-utm_source' };
    }
    if (key.startsWith('routing_rules')) {
        const match = key.match(
            /^routing_rules\.(\d+)\.(rule_type|match_value|target_url)$/,
        );
        const suffix =
            match?.[2] === 'target_url'
                ? `routing-target-${match[1]}`
                : match?.[2] === 'rule_type'
                  ? `routing-type-${match[1]}`
                  : `routing-match-${match?.[1] ?? '0'}`;
        return { panel: 'retargeting', id: suffix };
    }
    if (key === 'access_code') return { panel: 'password', id: 'access-code' };
    if (key === 'available_from') {
        return { panel: 'schedule', id: 'available-from' };
    }
    if (key === 'expires_at') return { panel: 'schedule', id: 'expires-at' };
    if (key === 'max_clicks') {
        return { panel: 'schedule', id: 'maximum-clicks' };
    }
    if (key === 'approval_required') {
        return { panel: 'schedule', id: 'approval-required' };
    }

    return {};
}

function errorSummaryMessage(key: string, message: string): string {
    if (key.startsWith('routing_rules.')) {
        if (message.includes('target_url field is required')) {
            return 'Destination URL is required.';
        }
        if (message.includes('match_value field is required')) {
            return 'Choose a match value.';
        }
    }
    if (key === 'expires_at' && message.includes('must be a date after')) {
        return 'Expiry must be after availability.';
    }

    return message;
}

function errorLabel(key: string): string {
    if (key === 'plan') return 'Plan';
    if (key.startsWith('routing_rules')) return 'Retargeting';
    if (
        key.startsWith('utm_parameters') ||
        key.startsWith('custom_parameters')
    ) {
        return 'Tracking';
    }
    if (
        key === 'available_from' ||
        key === 'expires_at' ||
        key === 'max_clicks' ||
        key === 'approval_required'
    ) {
        return 'Schedule';
    }
    if (key === 'access_code') return 'Password';
    if (key === 'slug') return 'Back-half';
    if (key === 'tag_ids' || key === 'tag_ids.0') return 'Tags';
    if (key === 'custom_domain_id') return 'Link host';
    if (key === 'delivery_mode') return 'Type';
    if (key === 'link_page_id') return 'Published link page';
    if (key === 'link_overlay_id') return 'CTA overlay';

    return key
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (character) => character.toUpperCase());
}

function TagSelect({
    tagOptions,
    selectedIds,
    onChange,
    disabled,
    invalid,
}: {
    tagOptions: LinkTagOption[];
    selectedIds: number[];
    onChange: (ids: number[]) => void;
    disabled: boolean;
    invalid: boolean;
}) {
    const [open, setOpen] = useState(false);

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button
                    id="link-tags"
                    type="button"
                    variant="outline"
                    role="combobox"
                    aria-expanded={open}
                    aria-invalid={invalid}
                    disabled={disabled}
                    className={cn(
                        'h-10 w-full justify-between px-3 font-normal',
                        selectedIds.length === 0 && 'text-muted-foreground',
                    )}
                >
                    <span className="truncate">
                        {selectedIds.length === 0
                            ? 'Select tags'
                            : selectedIds.length === 1
                              ? '1 tag selected'
                              : `${selectedIds.length} tags selected`}
                    </span>
                    <ChevronsUpDown
                        className="size-4 opacity-50"
                        aria-hidden="true"
                    />
                </Button>
            </PopoverTrigger>
            <PopoverContent
                align="start"
                className="z-[60] w-[var(--radix-popover-trigger-width)] p-2"
            >
                {tagOptions.length === 0 ? (
                    <p className="p-2 text-sm text-muted-foreground">
                        Create a tag from the link list to organize this link.
                    </p>
                ) : (
                    <div className="flex max-h-64 flex-col gap-0.5 overflow-y-auto">
                        {tagOptions.map((tag) => {
                            const checked = selectedIds.includes(tag.id);
                            const atLimit =
                                !checked && selectedIds.length >= 10;

                            return (
                                <label
                                    key={tag.id}
                                    className="flex min-h-10 cursor-pointer items-center gap-2 rounded-lg px-2 text-sm hover:bg-surface-secondary"
                                >
                                    <Checkbox
                                        checked={checked}
                                        disabled={disabled || atLimit}
                                        onCheckedChange={(value) =>
                                            onChange(
                                                value === true
                                                    ? [...selectedIds, tag.id]
                                                    : selectedIds.filter(
                                                          (id) => id !== tag.id,
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
                <p className="px-2 pt-2 text-xs text-muted-foreground">
                    Select up to 10 tags.
                </p>
            </PopoverContent>
        </Popover>
    );
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
    countries,
    open: controlledOpen,
    onOpenChange,
}: LinkFormDialogProps) {
    const platform = usePage().props.platform;
    const customDomainsEnabled = platform?.capabilities.domains !== false;
    const [uncontrolledOpen, setUncontrolledOpen] = useState(false);
    const open = controlledOpen ?? uncontrolledOpen;
    const { editorUrl } = useContentEditorRoutes();
    const [settingsPanel, setSettingsPanel] =
        useState<LinkSettingsPanel | null>(null);
    const form = useForm<LinkFormData>(
        formDataFor(link, platform?.defaultDelivery),
    );
    const isEditing = link !== undefined;
    const settingsTriggerRef = useRef<HTMLButtonElement | null>(null);
    const errorSummaryRef = useRef<HTMLDivElement | null>(null);
    const errors = form.errors as Record<string, string | undefined>;
    const errorEntries = Object.entries(errors).filter(
        (entry): entry is [string, string] => Boolean(entry[1]),
    );
    const preview = previewDestination(
        form.data.destination_url,
        form.data.utm_parameters,
        form.data.custom_parameters,
    );
    const accessCodeConfigured =
        !form.data.clear_access_code &&
        (form.data.access_code !== '' || Boolean(link?.hasAccessCode));
    const trackingConfigured =
        Object.values(form.data.utm_parameters).some(
            (value) => value.trim() !== '',
        ) ||
        form.data.custom_parameters.some(
            (parameter) =>
                parameter.key.trim() !== '' && parameter.value !== '',
        );
    const scheduleConfigured =
        form.data.available_from !== '' ||
        form.data.expires_at !== '' ||
        form.data.max_clicks !== '' ||
        form.data.approval_required;
    const settingsActions = (
        [
            {
                panel: 'tracking',
                label: 'Tracking',
                icon: ChartNoAxesCombined,
                configured: trackingConfigured,
            },
            {
                panel: 'retargeting',
                label: 'Retargeting',
                icon: Crosshair,
                configured: form.data.routing_rules.length > 0,
            },
            {
                panel: 'password',
                label: 'Password',
                icon: LockKeyhole,
                configured: accessCodeConfigured,
            },
            {
                panel: 'schedule',
                label: 'Schedule',
                icon: CalendarClock,
                configured: scheduleConfigured,
            },
        ] satisfies Array<{
            panel: LinkSettingsPanel;
            label: string;
            icon: LucideIcon;
            configured: boolean;
        }>
    ).filter(
        (action) =>
            action.panel !== 'retargeting' ||
            platform?.capabilities.targeting !== false,
    );

    function handleOpenChange(nextOpen: boolean) {
        if (form.processing) {
            return;
        }

        setUncontrolledOpen(nextOpen);
        onOpenChange?.(nextOpen);

        if (nextOpen) {
            form.clearErrors();
            form.setData(formDataFor(link, platform?.defaultDelivery));
            setSettingsPanel(null);
        }
    }

    function openSettings(panel: LinkSettingsPanel, focusId?: string) {
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

    function commitTracking(
        next: Pick<LinkFormData, 'utm_parameters' | 'custom_parameters'>,
    ) {
        form.setData({ ...form.data, ...next });
        form.clearErrors('utm_parameters');
        form.clearErrors('custom_parameters');
        setSettingsPanel(null);
    }

    function commitRetargeting(rules: LinkFormData['routing_rules']) {
        form.setData('routing_rules', rules);
        Object.keys(form.errors)
            .filter((key) => key.startsWith('routing_rules'))
            .forEach((key) => form.clearErrors(key as keyof LinkFormData));
        setSettingsPanel(null);
    }

    function commitPassword(
        next: Pick<LinkFormData, 'access_code' | 'clear_access_code'>,
    ) {
        form.setData({ ...form.data, ...next });
        form.clearErrors('access_code');
        setSettingsPanel(null);
    }

    function commitSchedule(
        next: Pick<
            LinkFormData,
            'available_from' | 'expires_at' | 'max_clicks' | 'approval_required'
        >,
    ) {
        form.setData({ ...form.data, ...next });
        form.clearErrors('available_from');
        form.clearErrors('expires_at');
        form.clearErrors('max_clicks');
        form.clearErrors('approval_required');
        setSettingsPanel(null);
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
                setUncontrolledOpen(false);
                onOpenChange?.(false);
                form.reset();
            },
            onError: () => {
                window.requestAnimationFrame(() =>
                    errorSummaryRef.current?.focus(),
                );
            },
        };

        if (link) {
            form.patch(
                editorUrl(withListQuery(update(link.id).url, listQuery)),
                options,
            );

            return;
        }

        form.post(withListQuery(store().url, listQuery), options);
    }

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            {children && <DialogTrigger asChild>{children}</DialogTrigger>}
            <DialogContent
                className="flex max-h-[calc(100dvh-2rem)] flex-col gap-0 overflow-hidden p-0 sm:max-w-[40rem]"
                onOpenAutoFocus={(event) => {
                    event.preventDefault();

                    if (window.matchMedia('(max-width: 640px)').matches) {
                        document
                            .getElementById('link-form-dialog-title')
                            ?.focus();

                        return;
                    }

                    const target =
                        form.data.delivery_mode === 'link_page'
                            ? 'link-page'
                            : 'destination-url';
                    document.getElementById(target)?.focus();
                }}
            >
                <DialogHeader className="shrink-0 px-6 pt-6 pr-14 pb-4">
                    <DialogTitle
                        id="link-form-dialog-title"
                        tabIndex={-1}
                        className="flex items-center gap-2"
                    >
                        <Link2 className="size-4" aria-hidden="true" />
                        {isEditing ? 'Edit short link' : 'Create short link'}
                    </DialogTitle>
                    <DialogDescription className="sr-only">
                        Set a destination, then configure optional tracking and
                        other available controls.
                    </DialogDescription>
                </DialogHeader>

                <FormErrorSummary
                    summaryRef={errorSummaryRef}
                    titleId="link-form-errors-title"
                    entries={errorEntries.map(([key, message]) => {
                        const target = errorTarget(key);

                        return {
                            key,
                            label: errorLabel(key),
                            message: errorSummaryMessage(key, message),
                            interactive: Boolean(target.panel || target.id),
                        };
                    })}
                    onSelect={handleErrorEntry}
                />

                <form
                    onSubmit={submit}
                    className="flex min-h-0 flex-1 flex-col"
                >
                    <div className="min-h-0 flex-1 overflow-y-auto px-6 py-1">
                        <div className="flex flex-col gap-5">
                            {form.data.delivery_mode !== 'link_page' && (
                                <Field
                                    data-invalid={Boolean(
                                        errors.destination_url,
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
                                            errors.destination_url,
                                        )}
                                    />
                                    {errors.destination_url && (
                                        <FieldError>
                                            {errors.destination_url}
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

                            <div className="grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)]">
                                <Field
                                    data-invalid={Boolean(
                                        errors.custom_domain_id,
                                    )}
                                >
                                    <FieldLabel htmlFor="link-custom-domain">
                                        Link host
                                    </FieldLabel>
                                    <Select
                                        value={
                                            form.data.custom_domain_id ||
                                            'default'
                                        }
                                        onValueChange={(value) =>
                                            form.setData(
                                                'custom_domain_id',
                                                value === 'default'
                                                    ? ''
                                                    : value,
                                            )
                                        }
                                        disabled={form.processing}
                                    >
                                        <SelectTrigger
                                            id="link-custom-domain"
                                            className="h-10 w-full"
                                            aria-invalid={Boolean(
                                                errors.custom_domain_id,
                                            )}
                                            aria-describedby="link-host-help"
                                        >
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
                                                    disabled={
                                                        !customDomainsEnabled &&
                                                        domain.id.toString() !==
                                                            form.data
                                                                .custom_domain_id
                                                    }
                                                >
                                                    {domain.hostname}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </Field>
                                <span
                                    aria-hidden="true"
                                    className="hidden self-end pb-2.5 text-center text-muted-foreground sm:block"
                                >
                                    /
                                </span>
                                <Field data-invalid={Boolean(errors.slug)}>
                                    <FieldLabel htmlFor="custom-slug">
                                        Back-half
                                        {isEditing ? '' : ' (optional)'}
                                    </FieldLabel>
                                    <Input
                                        id="custom-slug"
                                        value={form.data.slug}
                                        onChange={(event) =>
                                            form.setData(
                                                'slug',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="spring-launch"
                                        maxLength={32}
                                        autoComplete="off"
                                        required={isEditing}
                                        disabled={form.processing}
                                        aria-invalid={Boolean(errors.slug)}
                                        aria-describedby="back-half-help"
                                    />
                                </Field>
                            </div>
                            <div className="grid gap-3 sm:grid-cols-2">
                                <div className="flex flex-col gap-1">
                                    <FieldDescription
                                        id="link-host-help"
                                        className="text-xs"
                                    >
                                        {customDomainsEnabled
                                            ? 'Only active, verified domains are available.'
                                            : 'Custom domains are disabled by platform policy.'}
                                    </FieldDescription>
                                    {errors.custom_domain_id && (
                                        <FieldError>
                                            {errors.custom_domain_id}
                                        </FieldError>
                                    )}
                                </div>
                                <div className="flex flex-col gap-1">
                                    <FieldDescription
                                        id="back-half-help"
                                        className="text-xs"
                                    >
                                        {isEditing
                                            ? 'Required for existing links. Use 4–32 lowercase letters, numbers, or hyphens.'
                                            : 'Optional. Use 4–32 lowercase letters, numbers, or hyphens.'}
                                    </FieldDescription>
                                    {errors.slug && (
                                        <FieldError>{errors.slug}</FieldError>
                                    )}
                                </div>
                            </div>

                            <Field data-invalid={Boolean(errors.delivery_mode)}>
                                <FieldLabel htmlFor="link-delivery-mode">
                                    Type
                                </FieldLabel>
                                <Select
                                    value={form.data.delivery_mode}
                                    onValueChange={(value) =>
                                        form.setData(
                                            'delivery_mode',
                                            value as LinkDeliveryMode,
                                        )
                                    }
                                    disabled={form.processing}
                                >
                                    <SelectTrigger
                                        id="link-delivery-mode"
                                        className="h-10 w-full"
                                    >
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
                                    Only the selected type&apos;s target is
                                    saved.
                                </FieldDescription>
                                {errors.delivery_mode && (
                                    <FieldError>
                                        {errors.delivery_mode}
                                    </FieldError>
                                )}
                            </Field>

                            {form.data.delivery_mode === 'overlay' && (
                                <Field
                                    data-invalid={Boolean(
                                        errors.link_overlay_id,
                                    )}
                                >
                                    <FieldLabel htmlFor="link-overlay">
                                        CTA overlay
                                    </FieldLabel>
                                    <Select
                                        value={
                                            form.data.link_overlay_id || 'none'
                                        }
                                        onValueChange={(value) =>
                                            form.setData(
                                                'link_overlay_id',
                                                value === 'none' ? '' : value,
                                            )
                                        }
                                        disabled={form.processing}
                                    >
                                        <SelectTrigger
                                            id="link-overlay"
                                            className="h-10 w-full"
                                        >
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
                                    {errors.link_overlay_id && (
                                        <FieldError>
                                            {errors.link_overlay_id}
                                        </FieldError>
                                    )}
                                </Field>
                            )}

                            {form.data.delivery_mode === 'link_page' && (
                                <Field
                                    data-invalid={Boolean(errors.link_page_id)}
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
                                        <SelectTrigger
                                            id="link-page"
                                            className="h-10 w-full"
                                        >
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
                                        Link-page delivery records the visit
                                        before sending visitors to this
                                        published page.
                                    </FieldDescription>
                                    {errors.link_page_id && (
                                        <FieldError>
                                            {errors.link_page_id}
                                        </FieldError>
                                    )}
                                </Field>
                            )}

                            <Field data-invalid={Boolean(errors.title)}>
                                <FieldLabel htmlFor="link-title">
                                    Title (optional)
                                </FieldLabel>
                                <Input
                                    id="link-title"
                                    value={form.data.title}
                                    onChange={(event) =>
                                        form.setData(
                                            'title',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="Spring campaign"
                                    maxLength={160}
                                    autoComplete="off"
                                    disabled={form.processing}
                                    aria-invalid={Boolean(errors.title)}
                                />
                                {errors.title && (
                                    <FieldError>{errors.title}</FieldError>
                                )}
                            </Field>

                            <div className="grid gap-3 sm:grid-cols-2">
                                <Field data-invalid={Boolean(errors.folder_id)}>
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
                                        <SelectTrigger
                                            id="link-folder"
                                            className="h-10 w-full"
                                        >
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
                                    {errors.folder_id && (
                                        <FieldError>
                                            {errors.folder_id}
                                        </FieldError>
                                    )}
                                </Field>
                                <Field
                                    data-invalid={Boolean(errors['tag_ids.0'])}
                                >
                                    <FieldLabel htmlFor="link-tags">
                                        Tags
                                    </FieldLabel>
                                    <TagSelect
                                        tagOptions={tagOptions}
                                        selectedIds={form.data.tag_ids}
                                        onChange={(ids) =>
                                            form.setData('tag_ids', ids)
                                        }
                                        disabled={form.processing}
                                        invalid={Boolean(errors['tag_ids.0'])}
                                    />
                                    {errors['tag_ids.0'] && (
                                        <FieldError>
                                            {errors['tag_ids.0']}
                                        </FieldError>
                                    )}
                                </Field>
                            </div>
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
                                {isEditing ? 'Save changes' : 'Create link'}
                            </Button>
                        </div>
                    </div>
                </form>

                <LinkFormSettingsDialogs
                    activePanel={settingsPanel}
                    onActivePanelChange={setSettingsPanel}
                    settingsTriggerRef={settingsTriggerRef}
                    data={form.data}
                    errors={errors}
                    processing={form.processing}
                    hasExistingAccessCode={link?.hasAccessCode ?? false}
                    canManageApprovals={canManageApprovals}
                    countries={countries}
                    onCommitTracking={commitTracking}
                    onCommitRetargeting={commitRetargeting}
                    onCommitPassword={(next) => commitPassword(next)}
                    onCommitSchedule={commitSchedule}
                />
            </DialogContent>
        </Dialog>
    );
}
