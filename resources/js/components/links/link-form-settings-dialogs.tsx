import {
    ArrowDown,
    ArrowUp,
    Check,
    ChevronsUpDown,
    Plus,
    Trash2,
} from 'lucide-react';
import { type RefObject, useRef, useState } from 'react';
import { SettingsPanelDialog as PanelShell } from '@/components/forms/settings-panel-dialog';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
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
import { cn } from '@/lib/utils';
import type { CountryOption } from '@/types/links';
import {
    type CustomParameterInput,
    type LinkFormData,
    type RoutingRuleInput,
    previewDestination,
} from './link-form-data';

export type LinkSettingsPanel =
    | 'tracking'
    | 'retargeting'
    | 'password'
    | 'schedule';

type LinkFormSettingsDialogsProps = {
    activePanel: LinkSettingsPanel | null;
    onActivePanelChange: (panel: LinkSettingsPanel | null) => void;
    settingsTriggerRef: RefObject<HTMLButtonElement | null>;
    data: LinkFormData;
    errors: Record<string, string | undefined>;
    processing: boolean;
    hasExistingAccessCode: boolean;
    canManageApprovals: boolean;
    countries: CountryOption[];
    onCommitTracking: (
        next: Pick<LinkFormData, 'utm_parameters' | 'custom_parameters'>,
    ) => void;
    onCommitRetargeting: (rules: RoutingRuleInput[]) => void;
    onCommitPassword: (
        next: Pick<LinkFormData, 'access_code' | 'clear_access_code'>,
        enabled: boolean,
    ) => void;
    onCommitSchedule: (
        next: Pick<
            LinkFormData,
            'available_from' | 'expires_at' | 'max_clicks' | 'approval_required'
        >,
    ) => void;
};

const DEVICE_OPTIONS = [
    { value: 'mobile', label: 'Mobile' },
    { value: 'tablet', label: 'Tablet' },
    { value: 'desktop', label: 'Desktop' },
] as const;

const PLATFORM_OPTIONS = [
    { value: 'android', label: 'Android' },
    { value: 'ios', label: 'iOS' },
    { value: 'chromeos', label: 'ChromeOS' },
    { value: 'windows', label: 'Windows' },
    { value: 'macos', label: 'macOS' },
    { value: 'linux', label: 'Linux' },
] as const;

const UTM_FIELDS = [
    ['utm_source', 'Source'],
    ['utm_medium', 'Medium'],
    ['utm_campaign', 'Campaign'],
    ['utm_term', 'Term'],
    ['utm_content', 'Content'],
] as const;

function routingFieldError(message: string | undefined): string | undefined {
    if (!message) {
        return message;
    }
    if (message.includes('target_url field is required')) {
        return 'Destination URL is required.';
    }
    if (message.includes('match_value field is required')) {
        return 'Choose a match value.';
    }

    return message;
}

function CountrySelect({
    id,
    value,
    onChange,
    countries,
    disabled,
    invalid,
}: {
    id: string;
    value: string;
    onChange: (value: string) => void;
    countries: CountryOption[];
    disabled: boolean;
    invalid: boolean;
}) {
    const [open, setOpen] = useState(false);
    const selected = countries.find((country) => country.code === value);

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button
                    id={id}
                    type="button"
                    variant="outline"
                    role="combobox"
                    aria-expanded={open}
                    aria-invalid={invalid}
                    disabled={disabled}
                    className={cn(
                        'h-10 w-full justify-between px-3 font-normal',
                        !selected && 'text-muted-foreground',
                    )}
                >
                    <span className="truncate">
                        {selected?.name ?? 'Select country'}
                    </span>
                    <ChevronsUpDown
                        className="size-4 opacity-50"
                        aria-hidden="true"
                    />
                </Button>
            </PopoverTrigger>
            <PopoverContent
                align="start"
                className="z-[70] w-[var(--radix-popover-trigger-width)] p-0"
            >
                <Command>
                    <CommandInput
                        aria-label="Search countries"
                        placeholder="Search countries"
                    />
                    <CommandList className="max-h-60">
                        <CommandEmpty>No country found.</CommandEmpty>
                        <CommandGroup>
                            {countries.map((country) => (
                                <CommandItem
                                    key={country.code}
                                    value={`${country.code} ${country.name}`}
                                    onSelect={() => {
                                        onChange(country.code);
                                        setOpen(false);
                                    }}
                                >
                                    <Check
                                        className={cn(
                                            'size-4',
                                            value === country.code
                                                ? 'opacity-100'
                                                : 'opacity-0',
                                        )}
                                        aria-hidden="true"
                                    />
                                    <span>{country.name}</span>
                                    <span className="ml-auto text-xs text-muted-foreground">
                                        {country.code}
                                    </span>
                                </CommandItem>
                            ))}
                        </CommandGroup>
                    </CommandList>
                </Command>
            </PopoverContent>
        </Popover>
    );
}

function TrackingPanel({
    data,
    errors,
    processing,
    close,
    triggerRef,
    onSave,
}: {
    data: LinkFormData;
    errors: Record<string, string | undefined>;
    processing: boolean;
    close: () => void;
    triggerRef: RefObject<HTMLButtonElement | null>;
    onSave: (
        next: Pick<LinkFormData, 'utm_parameters' | 'custom_parameters'>,
    ) => void;
}) {
    const [utm, setUtm] = useState<Record<string, string>>({
        ...data.utm_parameters,
    });
    const [custom, setCustom] = useState<CustomParameterInput[]>(
        data.custom_parameters.map((parameter) => ({ ...parameter })),
    );
    const preview = previewDestination(data.destination_url, utm, custom);

    function updateCustom(
        index: number,
        changes: Partial<CustomParameterInput>,
    ) {
        setCustom((current) =>
            current.map((parameter, parameterIndex) =>
                parameterIndex === index
                    ? { ...parameter, ...changes }
                    : parameter,
            ),
        );
    }

    return (
        <PanelShell
            title="Tracking"
            description="Add campaign parameters without loading the destination."
            close={close}
            triggerRef={triggerRef}
            footer={
                <>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={close}
                        disabled={processing}
                    >
                        Close
                    </Button>
                    <Button
                        type="button"
                        onClick={() =>
                            onSave({
                                utm_parameters: utm,
                                custom_parameters: custom,
                            })
                        }
                        disabled={processing}
                    >
                        Save changes
                    </Button>
                </>
            }
        >
            <div className="flex flex-col gap-5">
                <div className="grid gap-4 sm:grid-cols-2">
                    {UTM_FIELDS.map(([key, label]) => (
                        <Field key={key}>
                            <FieldLabel htmlFor={`utm-${key}`}>
                                {label}
                            </FieldLabel>
                            <Input
                                id={`utm-${key}`}
                                value={utm[key] ?? ''}
                                maxLength={512}
                                disabled={processing}
                                onChange={(event) =>
                                    setUtm((current) => ({
                                        ...current,
                                        [key]: event.target.value,
                                    }))
                                }
                                aria-invalid={Boolean(
                                    errors[`utm_parameters.${key}`],
                                )}
                            />
                            {errors[`utm_parameters.${key}`] && (
                                <FieldError>
                                    {errors[`utm_parameters.${key}`]}
                                </FieldError>
                            )}
                        </Field>
                    ))}
                </div>

                <div className="flex flex-col gap-3">
                    <div className="flex flex-col gap-1">
                        <h3 className="text-sm font-medium">
                            Custom query parameters
                        </h3>
                        <FieldDescription>
                            Add up to 10 key/value pairs. Preview is local; the
                            destination is never loaded here.
                        </FieldDescription>
                    </div>
                    {custom.map((parameter, index) => (
                        <div
                            key={index}
                            className="grid gap-2 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto]"
                        >
                            <Input
                                id={`custom-parameter-${index}-key`}
                                aria-label={`Custom parameter ${index + 1} name`}
                                value={parameter.key}
                                placeholder="ref"
                                maxLength={64}
                                disabled={processing}
                                onChange={(event) =>
                                    updateCustom(index, {
                                        key: event.target.value,
                                    })
                                }
                            />
                            <Input
                                id={`custom-parameter-${index}-value`}
                                aria-label={`Custom parameter ${index + 1} value`}
                                value={parameter.value}
                                placeholder="newsletter"
                                maxLength={512}
                                disabled={processing}
                                onChange={(event) =>
                                    updateCustom(index, {
                                        value: event.target.value,
                                    })
                                }
                            />
                            <Button
                                type="button"
                                variant="outline"
                                size="icon"
                                aria-label={`Remove custom parameter ${index + 1}`}
                                onClick={() =>
                                    setCustom((current) =>
                                        current.filter(
                                            (_, parameterIndex) =>
                                                parameterIndex !== index,
                                        ),
                                    )
                                }
                                disabled={processing}
                            >
                                <Trash2 aria-hidden="true" />
                            </Button>
                        </div>
                    ))}
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        className="w-fit"
                        onClick={() =>
                            setCustom((current) => [
                                ...current,
                                { key: '', value: '' },
                            ])
                        }
                        disabled={processing || custom.length >= 10}
                    >
                        <Plus aria-hidden="true" />
                        Add parameter
                    </Button>
                    {errors.custom_parameters && (
                        <FieldError>{errors.custom_parameters}</FieldError>
                    )}
                </div>

                {preview && (
                    <div className="rounded-xl border border-border bg-surface-secondary p-3">
                        <p className="text-xs font-medium text-muted-foreground">
                            Redirect preview
                        </p>
                        <code className="mt-1 block text-xs break-all text-foreground">
                            {preview}
                        </code>
                    </div>
                )}
            </div>
        </PanelShell>
    );
}

function RetargetingPanel({
    data,
    errors,
    countries,
    processing,
    close,
    triggerRef,
    onSave,
}: {
    data: LinkFormData;
    errors: Record<string, string | undefined>;
    countries: CountryOption[];
    processing: boolean;
    close: () => void;
    triggerRef: RefObject<HTMLButtonElement | null>;
    onSave: (rules: RoutingRuleInput[]) => void;
}) {
    const [rules, setRules] = useState<RoutingRuleInput[]>(() =>
        data.routing_rules.map((rule) => ({ ...rule })),
    );
    const [keys, setKeys] = useState<number[]>(() =>
        data.routing_rules.map((_, index) => index),
    );
    const nextKey = useRef(data.routing_rules.length);

    function addRule(type: RoutingRuleInput['rule_type']) {
        if (rules.length >= 25) {
            return;
        }

        setRules((current) => [
            ...current,
            { rule_type: type, match_value: '', target_url: '' },
        ]);
        setKeys((current) => [...current, nextKey.current++]);
    }

    function updateRule(index: number, changes: Partial<RoutingRuleInput>) {
        setRules((current) =>
            current.map((rule, ruleIndex) =>
                ruleIndex === index ? { ...rule, ...changes } : rule,
            ),
        );
    }

    function moveRule(index: number, offset: -1 | 1) {
        const nextIndex = index + offset;

        if (nextIndex < 0 || nextIndex >= rules.length) {
            return;
        }

        setRules((current) => {
            const next = [...current];
            [next[index], next[nextIndex]] = [next[nextIndex], next[index]];
            return next;
        });
        setKeys((current) => {
            const next = [...current];
            [next[index], next[nextIndex]] = [next[nextIndex], next[index]];
            return next;
        });
    }

    function removeRule(index: number) {
        setRules((current) =>
            current.filter((_, ruleIndex) => ruleIndex !== index),
        );
        setKeys((current) =>
            current.filter((_, ruleIndex) => ruleIndex !== index),
        );
    }

    return (
        <PanelShell
            title="Retargeting"
            description="Send visitors to a different destination based on their location, device, or platform. The first matching rule wins."
            close={close}
            triggerRef={triggerRef}
            footer={
                <>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={close}
                        disabled={processing}
                    >
                        Close
                    </Button>
                    <Button
                        type="button"
                        onClick={() => onSave(rules)}
                        disabled={processing}
                    >
                        Save changes
                    </Button>
                </>
            }
        >
            <div className="flex flex-col gap-5">
                <div className="flex flex-wrap gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() => addRule('country')}
                        disabled={processing || rules.length >= 25}
                    >
                        <Plus aria-hidden="true" />
                        Add location
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() => addRule('device')}
                        disabled={processing || rules.length >= 25}
                    >
                        <Plus aria-hidden="true" />
                        Add device
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() => addRule('platform')}
                        disabled={processing || rules.length >= 25}
                    >
                        <Plus aria-hidden="true" />
                        Add platform
                    </Button>
                </div>

                {rules.length === 0 ? (
                    <div className="rounded-xl border border-dashed border-border p-5 text-sm text-muted-foreground">
                        No rules yet. Add a location, device, or platform rule
                        to send different visitors to different destinations.
                    </div>
                ) : (
                    <div className="flex flex-col gap-3">
                        <h3 className="text-sm font-medium">
                            Rules in delivery order
                        </h3>
                        {rules.map((rule, index) => (
                            <div
                                key={keys[index] ?? index}
                                className="flex flex-col gap-3 rounded-xl border border-border p-3"
                            >
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <span className="text-sm font-medium">
                                        Rule {index + 1} · Priority {index + 1}
                                    </span>
                                    <div className="flex gap-1">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="icon"
                                            aria-label={`Move rule ${index + 1} up`}
                                            onClick={() => moveRule(index, -1)}
                                            disabled={processing || index === 0}
                                        >
                                            <ArrowUp aria-hidden="true" />
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="icon"
                                            aria-label={`Move rule ${index + 1} down`}
                                            onClick={() => moveRule(index, 1)}
                                            disabled={
                                                processing ||
                                                index === rules.length - 1
                                            }
                                        >
                                            <ArrowDown aria-hidden="true" />
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="icon"
                                            aria-label={`Remove rule ${index + 1}`}
                                            onClick={() => removeRule(index)}
                                            disabled={processing}
                                        >
                                            <Trash2 aria-hidden="true" />
                                        </Button>
                                    </div>
                                </div>

                                <div className="grid gap-3 md:grid-cols-[9rem_minmax(0,1fr)_minmax(0,1.3fr)]">
                                    <Field>
                                        <FieldLabel
                                            htmlFor={`routing-type-${index}`}
                                        >
                                            Type
                                        </FieldLabel>
                                        <Select
                                            value={rule.rule_type}
                                            onValueChange={(value) =>
                                                updateRule(index, {
                                                    rule_type:
                                                        value as RoutingRuleInput['rule_type'],
                                                    match_value: '',
                                                })
                                            }
                                            disabled={processing}
                                        >
                                            <SelectTrigger
                                                id={`routing-type-${index}`}
                                                className="h-10 w-full"
                                            >
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent className="z-[70]">
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
                                    </Field>

                                    <Field
                                        data-invalid={Boolean(
                                            errors[
                                                `routing_rules.${index}.match_value`
                                            ],
                                        )}
                                    >
                                        <FieldLabel
                                            htmlFor={`routing-match-${index}`}
                                        >
                                            {rule.rule_type === 'country'
                                                ? 'Country'
                                                : rule.rule_type === 'device'
                                                  ? 'Device'
                                                  : 'Platform'}
                                        </FieldLabel>
                                        {rule.rule_type === 'country' ? (
                                            <CountrySelect
                                                id={`routing-match-${index}`}
                                                value={rule.match_value}
                                                onChange={(value) =>
                                                    updateRule(index, {
                                                        match_value: value,
                                                    })
                                                }
                                                countries={countries}
                                                disabled={processing}
                                                invalid={Boolean(
                                                    errors[
                                                        `routing_rules.${index}.match_value`
                                                    ],
                                                )}
                                            />
                                        ) : (
                                            <Select
                                                value={rule.match_value}
                                                onValueChange={(value) =>
                                                    updateRule(index, {
                                                        match_value: value,
                                                    })
                                                }
                                                disabled={processing}
                                            >
                                                <SelectTrigger
                                                    id={`routing-match-${index}`}
                                                    className="h-10 w-full"
                                                    aria-invalid={Boolean(
                                                        errors[
                                                            `routing_rules.${index}.match_value`
                                                        ],
                                                    )}
                                                >
                                                    <SelectValue
                                                        placeholder={
                                                            rule.rule_type ===
                                                            'device'
                                                                ? 'Select device'
                                                                : 'Select platform'
                                                        }
                                                    />
                                                </SelectTrigger>
                                                <SelectContent className="z-[70]">
                                                    {(rule.rule_type ===
                                                    'device'
                                                        ? DEVICE_OPTIONS
                                                        : PLATFORM_OPTIONS
                                                    ).map((option) => (
                                                        <SelectItem
                                                            key={option.value}
                                                            value={option.value}
                                                        >
                                                            {option.label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        )}
                                        {errors[
                                            `routing_rules.${index}.match_value`
                                        ] && (
                                            <FieldError>
                                                {routingFieldError(
                                                    errors[
                                                        `routing_rules.${index}.match_value`
                                                    ],
                                                )}
                                            </FieldError>
                                        )}
                                    </Field>

                                    <Field
                                        data-invalid={Boolean(
                                            errors[
                                                `routing_rules.${index}.target_url`
                                            ],
                                        )}
                                    >
                                        <FieldLabel
                                            htmlFor={`routing-target-${index}`}
                                        >
                                            Destination URL
                                        </FieldLabel>
                                        <Input
                                            id={`routing-target-${index}`}
                                            type="url"
                                            value={rule.target_url}
                                            placeholder="https://example.com/mobile"
                                            maxLength={2048}
                                            disabled={processing}
                                            onChange={(event) =>
                                                updateRule(index, {
                                                    target_url:
                                                        event.target.value,
                                                })
                                            }
                                            aria-invalid={Boolean(
                                                errors[
                                                    `routing_rules.${index}.target_url`
                                                ],
                                            )}
                                        />
                                        {errors[
                                            `routing_rules.${index}.target_url`
                                        ] && (
                                            <FieldError>
                                                {routingFieldError(
                                                    errors[
                                                        `routing_rules.${index}.target_url`
                                                    ],
                                                )}
                                            </FieldError>
                                        )}
                                    </Field>
                                </div>
                            </div>
                        ))}
                    </div>
                )}

                {errors.routing_rules && (
                    <FieldError>{errors.routing_rules}</FieldError>
                )}
            </div>
        </PanelShell>
    );
}

function PasswordPanel({
    data,
    errors,
    processing,
    hasExistingAccessCode,
    close,
    triggerRef,
    onSave,
}: {
    data: LinkFormData;
    errors: Record<string, string | undefined>;
    processing: boolean;
    hasExistingAccessCode: boolean;
    close: () => void;
    triggerRef: RefObject<HTMLButtonElement | null>;
    onSave: (
        next: Pick<LinkFormData, 'access_code' | 'clear_access_code'>,
        enabled: boolean,
    ) => void;
}) {
    const canKeepCurrent =
        !data.clear_access_code &&
        (hasExistingAccessCode || data.access_code !== '');
    const [enabled, setEnabled] = useState(canKeepCurrent);
    const [code, setCode] = useState(data.access_code);
    const [localError, setLocalError] = useState('');
    const inputRef = useRef<HTMLInputElement>(null);

    function save() {
        if (enabled && !canKeepCurrent && code.trim() === '') {
            setLocalError('Enter an access code.');
            inputRef.current?.focus();
            return;
        }

        onSave(
            {
                access_code: enabled
                    ? code.trim() === ''
                        ? data.access_code
                        : code
                    : '',
                clear_access_code: !enabled && hasExistingAccessCode,
            },
            enabled,
        );
    }

    return (
        <PanelShell
            title="Password"
            description="Require a code before visitors can continue to this link."
            close={close}
            triggerRef={triggerRef}
            footer={
                <>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={close}
                        disabled={processing}
                    >
                        Close
                    </Button>
                    <Button type="button" onClick={save} disabled={processing}>
                        Save changes
                    </Button>
                </>
            }
        >
            <div className="flex flex-col gap-5">
                <Field data-invalid={Boolean(errors.access_code || localError)}>
                    <div className="flex items-start gap-3">
                        <Checkbox
                            id="require-access-code"
                            checked={enabled}
                            onCheckedChange={(checked) => {
                                setEnabled(checked === true);
                                setLocalError('');
                            }}
                            disabled={processing}
                        />
                        <div className="flex min-w-0 flex-1 flex-col gap-1">
                            <FieldLabel htmlFor="require-access-code">
                                Require an access code
                            </FieldLabel>
                            <FieldDescription>
                                Visitors must enter the code before this route
                                redirects.
                            </FieldDescription>
                        </div>
                    </div>
                    {enabled && (
                        <Field>
                            <FieldLabel htmlFor="access-code">
                                Access code
                            </FieldLabel>
                            <Input
                                ref={inputRef}
                                id="access-code"
                                type="password"
                                value={code}
                                onChange={(event) => {
                                    setCode(event.target.value);
                                    setLocalError('');
                                }}
                                placeholder={
                                    canKeepCurrent
                                        ? 'Enter a new code to replace the current one'
                                        : 'Set an access code'
                                }
                                autoComplete="new-password"
                                disabled={processing}
                                aria-invalid={Boolean(
                                    errors.access_code || localError,
                                )}
                            />
                            <FieldDescription>
                                {canKeepCurrent
                                    ? 'Leave blank to keep the current code.'
                                    : 'The code is required for this link.'}
                            </FieldDescription>
                            {(localError || errors.access_code) && (
                                <FieldError>
                                    {localError || errors.access_code}
                                </FieldError>
                            )}
                        </Field>
                    )}
                </Field>
            </div>
        </PanelShell>
    );
}

function SchedulePanel({
    data,
    errors,
    processing,
    canManageApprovals,
    close,
    triggerRef,
    onSave,
}: {
    data: LinkFormData;
    errors: Record<string, string | undefined>;
    processing: boolean;
    canManageApprovals: boolean;
    close: () => void;
    triggerRef: RefObject<HTMLButtonElement | null>;
    onSave: (
        next: Pick<
            LinkFormData,
            'available_from' | 'expires_at' | 'max_clicks' | 'approval_required'
        >,
    ) => void;
}) {
    const [availableFrom, setAvailableFrom] = useState(data.available_from);
    const [expiresAt, setExpiresAt] = useState(data.expires_at);
    const [maxClicks, setMaxClicks] = useState(data.max_clicks);
    const [approvalRequired, setApprovalRequired] = useState(
        data.approval_required,
    );

    return (
        <PanelShell
            title="Schedule & limits"
            description="Control when the link is available and how many clicks it can receive."
            close={close}
            triggerRef={triggerRef}
            footer={
                <>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={close}
                        disabled={processing}
                    >
                        Close
                    </Button>
                    <Button
                        type="button"
                        onClick={() =>
                            onSave({
                                available_from: availableFrom,
                                expires_at: expiresAt,
                                max_clicks: maxClicks,
                                approval_required: approvalRequired,
                            })
                        }
                        disabled={processing}
                    >
                        Save changes
                    </Button>
                </>
            }
        >
            <div className="flex flex-col gap-5">
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field data-invalid={Boolean(errors.available_from)}>
                        <FieldLabel htmlFor="available-from">
                            Available from
                        </FieldLabel>
                        <Input
                            id="available-from"
                            type="datetime-local"
                            value={availableFrom}
                            onChange={(event) =>
                                setAvailableFrom(event.target.value)
                            }
                            disabled={processing}
                            aria-invalid={Boolean(errors.available_from)}
                        />
                        <FieldDescription>
                            Leave empty to publish immediately.
                        </FieldDescription>
                        {errors.available_from && (
                            <FieldError>{errors.available_from}</FieldError>
                        )}
                    </Field>
                    <Field data-invalid={Boolean(errors.expires_at)}>
                        <FieldLabel htmlFor="expires-at">Expires at</FieldLabel>
                        <Input
                            id="expires-at"
                            type="datetime-local"
                            value={expiresAt}
                            onChange={(event) =>
                                setExpiresAt(event.target.value)
                            }
                            disabled={processing}
                            aria-invalid={Boolean(errors.expires_at)}
                        />
                        <FieldDescription>
                            Leave empty to keep the link live.
                        </FieldDescription>
                        {errors.expires_at && (
                            <FieldError>
                                {errors.expires_at.includes(
                                    'must be a date after',
                                )
                                    ? 'Expiry must be after availability.'
                                    : errors.expires_at}
                            </FieldError>
                        )}
                    </Field>
                </div>

                <Field data-invalid={Boolean(errors.max_clicks)}>
                    <FieldLabel htmlFor="maximum-clicks">
                        Maximum clicks
                    </FieldLabel>
                    <Input
                        id="maximum-clicks"
                        type="number"
                        min={1}
                        max={1_000_000_000}
                        step={1}
                        value={maxClicks}
                        onChange={(event) => setMaxClicks(event.target.value)}
                        disabled={processing}
                        placeholder="Unlimited"
                        aria-invalid={Boolean(errors.max_clicks)}
                    />
                    <FieldDescription>
                        The last permitted delivery succeeds; later requests are
                        blocked atomically.
                    </FieldDescription>
                    {errors.max_clicks && (
                        <FieldError>{errors.max_clicks}</FieldError>
                    )}
                </Field>

                {canManageApprovals && (
                    <Field>
                        <div className="flex items-start gap-3">
                            <Checkbox
                                id="approval-required"
                                checked={approvalRequired}
                                onCheckedChange={(checked) =>
                                    setApprovalRequired(checked === true)
                                }
                                disabled={processing}
                            />
                            <div className="flex min-w-0 flex-1 flex-col gap-1">
                                <FieldLabel htmlFor="approval-required">
                                    Require owner/admin review
                                </FieldLabel>
                                <FieldDescription>
                                    Proposed edits stay out of the live route
                                    until approved.
                                </FieldDescription>
                            </div>
                        </div>
                        {errors.approval_required && (
                            <FieldError>{errors.approval_required}</FieldError>
                        )}
                    </Field>
                )}
            </div>
        </PanelShell>
    );
}

export function LinkFormSettingsDialogs({
    activePanel,
    onActivePanelChange,
    settingsTriggerRef,
    data,
    errors,
    processing,
    hasExistingAccessCode,
    canManageApprovals,
    countries,
    onCommitTracking,
    onCommitRetargeting,
    onCommitPassword,
    onCommitSchedule,
}: LinkFormSettingsDialogsProps) {
    if (activePanel === null) {
        return null;
    }

    const close = () => onActivePanelChange(null);

    if (activePanel === 'tracking') {
        return (
            <TrackingPanel
                data={data}
                errors={errors}
                processing={processing}
                close={close}
                triggerRef={settingsTriggerRef}
                onSave={(next) => {
                    onCommitTracking(next);
                    close();
                }}
            />
        );
    }

    if (activePanel === 'retargeting') {
        return (
            <RetargetingPanel
                data={data}
                errors={errors}
                countries={countries}
                processing={processing}
                close={close}
                triggerRef={settingsTriggerRef}
                onSave={(rules) => {
                    onCommitRetargeting(rules);
                    close();
                }}
            />
        );
    }

    if (activePanel === 'password') {
        return (
            <PasswordPanel
                data={data}
                errors={errors}
                processing={processing}
                hasExistingAccessCode={hasExistingAccessCode}
                close={close}
                triggerRef={settingsTriggerRef}
                onSave={(next) => {
                    onCommitPassword(
                        next,
                        next.access_code !== '' || hasExistingAccessCode,
                    );
                    close();
                }}
            />
        );
    }

    return (
        <SchedulePanel
            data={data}
            errors={errors}
            processing={processing}
            canManageApprovals={canManageApprovals}
            close={close}
            triggerRef={settingsTriggerRef}
            onSave={(next) => {
                onCommitSchedule(next);
                close();
            }}
        />
    );
}
