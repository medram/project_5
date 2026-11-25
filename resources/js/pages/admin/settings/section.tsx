import { Head, router, usePage } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowUp,
    Check,
    ChevronsUpDown,
    CreditCard,
    Mail,
    Plus,
    Copy,
    Save,
    Trash2,
    Upload,
    X,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import SettingsLayout from '@/pages/admin/settings/layout';
import { collectSettingsChanges, isSettingVisible } from '@/pages/admin/settings/settings-form';
import type { FormValues, SettingValue } from '@/pages/admin/settings/settings-form';
import { FormErrorSummary } from '@/components/forms/form-error-summary';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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
    FieldGroup,
    FieldLabel,
    FieldLegend,
    FieldSet,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import {
    InputGroup,
    InputGroupAddon,
    InputGroupButton,
    InputGroupInput,
} from '@/components/ui/input-group';
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
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { cn } from '@/lib/utils';
import settings from '@/routes/admin/settings';
import type {
    AdminSettingField,
    AdminSettingsPageProps,
} from '@/types/admin-settings';

type OptionItem = { value: string; label: string; description?: string | null };

function initialValues(fields: AdminSettingField[]): FormValues {
    return Object.fromEntries(
        fields.map((field) => [
            field.key,
            field.secret
                ? ''
                : field.type === 'int_list'
                  ? Array.isArray(field.value)
                      ? field.value.map(Number)
                      : []
                  : field.type === 'list'
                    ? Array.isArray(field.value)
                        ? field.value.map(String)
                        : []
                    : (field.value ?? (field.type === 'bool' ? false : '')),
        ]),
    );
}

function optionSource(field: AdminSettingField): string | null {
    if (field.key.endsWith('_page_ids') || field.key.endsWith('_page_id')) {
        return 'published-pages';
    }

    if (field.key === 'timezone') {
        return 'timezones';
    }

    if (field.key === 'default_locale') {
        return 'locales';
    }

    if (field.control === 'combobox' || field.key === 'disk') {
        return 'storage-disks';
    }

    return null;
}

function optionUrl(source: string, query: string, selected: string[]): string {
    const params = new URLSearchParams({ q: query });
    selected.forEach((value) => params.append('selected[]', value));
    return '/admin/settings/options/' + source + '?' + params.toString();
}

export default function AdminSettingsSection({
    section,
    navigation,
    canManage,
}: AdminSettingsPageProps) {
    const fields = useMemo(
        () => section.groups.flatMap((group) => group.fields),
        [section.groups],
    );
    const initial = useMemo(
        () => initialValues(fields),
        // The version changes after a successful save; fields carry new values.
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [section.key, section.version],
    );
    const [values, setValues] = useState<FormValues>(initial);
    const [processing, setProcessing] = useState(false);
    const [uploading, setUploading] = useState<string | null>(null);
    const page = usePage();
    const errors = page.props.errors ?? {};
    const requestErrorRef = useRef<HTMLDivElement>(null);
    const summaryRef = useRef<HTMLDivElement>(null);
    const saveBarRef = useRef<HTMLDivElement>(null);
    const fieldErrors: Array<{ key: string; fieldKey: string; label: string; message: string; interactive: boolean }> = [];
    const requestErrors: Array<{ key: string; message: string }> = [];
    for (const [key, message] of Object.entries(errors)) {
        const match = /^values\.([^.]+)(?:\.\d+)?$/.exec(key);
        const field = match ? fields.find((candidate) => candidate.key === match[1]) : undefined;
        if (field) {
            fieldErrors.push({ key, fieldKey: field.key, label: field.label, message, interactive: isSettingVisible(field, values) });
        } else if (!((section.key === 'email' && canManage && (key === 'recipient' || key === 'email')) ||
            (section.key === 'subscriptions' && canManage && key === 'stripe'))) {
            requestErrors.push({ key, message });
        }
    }

    useEffect(() => {
        // A stale response contains the latest saved values, not the user's draft.
        if (!errors.version) {
            setValues(initialValues(fields));
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [section.key, section.version]);

    const dirty = JSON.stringify(values) !== JSON.stringify(initial);
    const changes = collectSettingsChanges(fields, values, initial);
    const canSave = Object.keys(changes.visible).length > 0;
    const readiness = section.readiness;
    const genericReadiness = readiness?.state === 'ready' &&
        readiness.items.length === 1 &&
        readiness.items[0].state === 'ready' &&
        readiness.items[0].label === 'Configuration' &&
        readiness.items[0].detail === 'Database-backed settings are available.';

    useEffect(() => {
        if (!dirty) {
            return;
        }

        const warn = (event: BeforeUnloadEvent) => {
            event.preventDefault();
            event.returnValue = '';
        };
        const unregister = router.on('before', (event) => {
            if (
                event.detail.visit.method === 'get' &&
                event.detail.visit.url.pathname.startsWith('/admin/settings') &&
                !window.confirm('Discard unsaved settings changes?')
            ) {
                return false;
            }
        });
        window.addEventListener('beforeunload', warn);

        return () => {
            unregister();
            window.removeEventListener('beforeunload', warn);
        };
    }, [dirty]);

    useEffect(() => {
        if (Object.keys(errors).length === 0) {
            return;
        }

        const frame = requestAnimationFrame(() => {
            const target = requestErrorRef.current ?? summaryRef.current ??
                (errors.recipient ? document.getElementById('test-email-recipient') : null);
            target?.focus({ preventScroll: true });
            target?.scrollIntoView({ block: 'center', behavior: 'auto' });
        });
        return () => cancelAnimationFrame(frame);
    }, [errors]);

    const setValue = (key: string, value: SettingValue) => {
        setValues((current) => ({ ...current, [key]: value }));
    };

    const save = () => {
        if (!canSave || errors.version) {
            return;
        }
        if (
            changes.hasHiddenChanges &&
            !window.confirm('Save visible changes and discard unsaved changes in hidden fields?')
        ) {
            return;
        }

        setProcessing(true);
        router.patch(
            settings.update(section.key).url,
            { version: section.version, values: changes.visible },
            {
                preserveScroll: true,
                onFinish: () => setProcessing(false),
            },
        );
    };

    const clearSecret = (field: AdminSettingField) => {
        if (
            !window.confirm(
                'Remove the stored ' + field.label.toLowerCase() + '?' +
                    (dirty ? ' Any unsaved changes on this page will be discarded.' : ''),
            )
        ) {
            return;
        }

        router.delete(
            '/admin/settings/' + section.key + '/secrets/' + field.key,
            {
                data: { version: section.version },
                preserveScroll: true,
            },
        );
    };

    const uploadAsset = (field: AdminSettingField, file: File) => {
        if (dirty && !window.confirm('Upload this asset now? Any unsaved changes on this page will be discarded.')) {
            return;
        }
        setUploading(field.key);
        router.post(
            '/admin/settings/' + section.key + '/assets/' + field.key,
            { version: section.version, file },
            {
                forceFormData: true,
                preserveScroll: true,
                onFinish: () => setUploading(null),
            },
        );
    };

    return (
        <>
            <Head title={section.label + ' settings'} />
            <SettingsLayout section={section.key} navigation={navigation}>
                <div
                    className="flex min-w-0 flex-col gap-5 pb-24 [&_button]:scroll-mb-32 [&_input]:scroll-mb-32 [&_textarea]:scroll-mb-32"
                    onFocusCapture={(event) => {
                        const target = event.target;
                        const visibleTarget = target instanceof HTMLInputElement && target.type === 'file'
                            ? target.labels?.[0] ?? target
                            : target;
                        requestAnimationFrame(() => {
                            const bar = saveBarRef.current;
                            if (document.activeElement === target && bar && !bar.contains(target) &&
                                visibleTarget.getBoundingClientRect().bottom > bar.getBoundingClientRect().top - 8) {
                                visibleTarget.scrollIntoView({ block: 'center', behavior: 'auto' });
                            }
                        });
                    }}
                >
                    <div className="flex flex-col gap-1">
                        <h2 className="text-lg font-semibold">{section.label}</h2>
                        {section.description ? (
                            <p className="max-w-3xl text-sm text-muted-foreground">
                                {section.description}
                            </p>
                        ) : null}
                    </div>

                    {!canManage ? (
                        <Alert>
                            <AlertTitle>View-only access</AlertTitle>
                            <AlertDescription>
                                You can inspect settings and readiness. Changing
                                values requires the manage permission.
                            </AlertDescription>
                        </Alert>
                    ) : null}

                    {readiness && !genericReadiness ? (
                        <Alert
                            variant={
                                section.readiness.state === 'incomplete'
                                    ? 'destructive'
                                    : 'default'
                            }
                            className={section.readiness.state === 'incomplete'
                                ? 'border-danger/30 bg-danger-soft text-foreground *:data-[slot=alert-description]:text-foreground'
                                : undefined}
                        >
                            <AlertTitle className="flex items-center gap-2">
                                Provider readiness
                                <Badge variant="outline">
                                    {section.readiness.state}
                                </Badge>
                            </AlertTitle>
                            <AlertDescription>
                                <ul className="flex flex-col gap-1">
                                    {section.readiness.items.map((item) => (
                                        <li
                                            key={item.label}
                                            className="flex flex-wrap gap-2"
                                        >
                                            <span className="font-medium">
                                                {item.label}:
                                            </span>
                                            <span>{item.detail}</span>
                                        </li>
                                    ))}
                                </ul>
                            </AlertDescription>
                        </Alert>
                    ) : null}

                    {requestErrors.length > 0 ? (
                        <Alert ref={requestErrorRef} tabIndex={-1} variant="destructive"
                            className="border-danger/30 bg-danger-soft text-foreground outline-none *:data-[slot=alert-description]:text-foreground focus-visible:ring-2 focus-visible:ring-ring/40">
                            <AlertTitle>Could not complete the request</AlertTitle>
                            <AlertDescription>
                                <ul>{requestErrors.map(({ key, message }) => <li key={key}>{message}</li>)}</ul>
                            </AlertDescription>
                        </Alert>
                    ) : null}
                    <FormErrorSummary
                        summaryRef={summaryRef}
                        titleId="settings-error-summary-title"
                        entries={fieldErrors}
                        className="mx-0 mb-0"
                        onSelect={(key) => {
                            const fieldKey = fieldErrors.find((entry) => entry.key === key)?.fieldKey;
                            const target = fieldKey ? document.getElementById('setting-' + fieldKey) : null;
                            target?.focus({ preventScroll: true });
                            target?.scrollIntoView({ block: 'center', behavior: 'auto' });
                        }}
                    />

                    {section.groups.map((group) => (
                        <Card key={group.key} className="min-w-0">
                            <CardHeader className="gap-1">
                                <CardTitle as="h3">{group.label}</CardTitle>
                                {group.description ? (
                                    <CardDescription>
                                        {group.description}
                                    </CardDescription>
                                ) : null}
                            </CardHeader>
                            <CardContent>
                                <FieldGroup className="grid min-w-0 gap-5 xl:grid-cols-2">
                                    {group.fields
                                        .filter((field) => isSettingVisible(field, values))
                                        .map((field) => (
                                            <SettingControl
                                                key={field.key}
                                                field={field}
                                                value={values[field.key]}
                                                error={fieldErrors.find((entry) => entry.fieldKey === field.key)?.message}
                                                disabled={!canManage}
                                                busy={uploading === field.key}
                                                onChange={(value) =>
                                                    setValue(field.key, value)
                                                }
                                                onClearSecret={() =>
                                                    clearSecret(field)
                                                }
                                                onUpload={(file) =>
                                                    uploadAsset(field, file)
                                                }
                                            />
                                        ))}
                                </FieldGroup>
                            </CardContent>
                        </Card>
                    ))}

                    {section.key === 'authentication' ? (
                        <OAuthCallbackPanel />
                    ) : null}
                    {section.key === 'email' && canManage ? (
                        <TestEmailPanel recipientError={errors.recipient} deliveryError={errors.email} />
                    ) : null}
                    {section.key === 'subscriptions' && canManage ? (
                        <TestStripePanel error={errors.stripe} />
                    ) : null}

                    {canManage ? (
                        <div ref={saveBarRef} className="sticky bottom-0 z-20 -mx-4 mt-1 border-t bg-background px-4 py-3 md:-mx-6 md:px-6">
                            <div className="flex flex-wrap items-center gap-3">
                                <Button
                                    type="button"
                                    className="min-h-11 md:min-h-9"
                                    disabled={!canSave || processing || Boolean(errors.version)}
                                    onClick={save}
                                >
                                    {processing ? (
                                        <Spinner data-icon="inline-start" />
                                    ) : (
                                        <Save
                                            data-icon="inline-start"
                                            aria-hidden="true"
                                        />
                                    )}
                                    {processing ? 'Saving…' : 'Save changes'}
                                </Button>
                                <p
                                    className="text-sm text-muted-foreground"
                                    aria-live="polite"
                                >
                                    {!dirty
                                        ? 'All changes saved'
                                        : canSave
                                          ? 'Unsaved changes'
                                          : changes.hasHiddenChanges
                                            ? 'Unsaved changes are in hidden fields. Change the related option to review them.'
                                            : 'Enter a non-empty replacement to save a secret.'}
                                </p>
                            </div>
                        </div>
                    ) : null}
                </div>
            </SettingsLayout>
        </>
    );
}

function OAuthCallbackPanel() {
    const [copied, setCopied] = useState(false);
    const callback =
        (typeof window === 'undefined' ? '' : window.location.origin) +
        '/auth/google/callback';

    return (
        <Card>
            <CardHeader>
                <CardTitle as="h3">Google OAuth callback URL</CardTitle>
                <CardDescription>
                    Register this exact callback URL in the Google Cloud
                    console.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <InputGroup>
                    <InputGroupInput
                        name="google_callback_url"
                        value={callback}
                        readOnly
                        aria-label="Google OAuth callback URL"
                    />
                    <InputGroupAddon align="inline-end">
                        <InputGroupButton
                            type="button"
                            size="sm"
                            className="min-h-11 md:min-h-9"
                            onClick={async () => {
                                await navigator.clipboard.writeText(callback);
                                setCopied(true);
                            }}
                        >
                            <Copy data-icon="inline-start" aria-hidden="true" />
                            {copied ? 'Copied' : 'Copy'}
                        </InputGroupButton>
                    </InputGroupAddon>
                </InputGroup>
            </CardContent>
        </Card>
    );
}

function TestEmailPanel({ recipientError, deliveryError }: { recipientError?: string; deliveryError?: string }) {
    const [recipient, setRecipient] = useState('');

    return (
        <Card>
            <CardHeader>
                <CardTitle as="h3">Test email delivery</CardTitle>
                <CardDescription>
                    Sends one plain message using the saved configuration. Save
                    changes before testing.
                </CardDescription>
            </CardHeader>
            <CardContent className="flex flex-col gap-3">
                <Field data-invalid={Boolean(recipientError)}>
                    <FieldLabel htmlFor="test-email-recipient">
                        Recipient
                    </FieldLabel>
                    <Input
                        className="min-h-11 md:min-h-10"
                        id="test-email-recipient"
                        name="recipient"
                        type="email"
                        autoComplete="email"
                        value={recipient}
                        aria-invalid={Boolean(recipientError)}
                        aria-describedby={recipientError ? 'test-email-recipient-error' : undefined}
                        onChange={(event) => setRecipient(event.target.value)}
                    />
                    {recipientError ? <FieldError id="test-email-recipient-error" role="alert">{recipientError}</FieldError> : null}
                </Field>
                <Button
                    type="button"
                    variant="outline"
                    className="min-h-11 md:min-h-9"
                    disabled={recipient === ''}
                    onClick={() =>
                        router.post(
                            '/admin/settings/email/test',
                            { recipient },
                            { preserveScroll: true },
                        )
                    }
                >
                    <Mail data-icon="inline-start" aria-hidden="true" />
                    Send test email
                </Button>
                {deliveryError ? <FieldError role="alert">{deliveryError}</FieldError> : null}
            </CardContent>
        </Card>
    );
}

function TestStripePanel({ error }: { error?: string }) {
    return (
        <Card>
            <CardHeader>
                <CardTitle as="h3">Validate Stripe credentials</CardTitle>
                <CardDescription>
                    Performs a read-only account request with the saved
                    settings. No checkout or subscription is created.
                </CardDescription>
            </CardHeader>
            <CardContent className="flex flex-col gap-3">
                <Button
                    type="button"
                    variant="outline"
                    className="min-h-11 md:min-h-9"
                    onClick={() =>
                        router.post(
                            '/admin/settings/subscriptions/test',
                            {},
                            { preserveScroll: true },
                        )
                    }
                >
                    <CreditCard data-icon="inline-start" aria-hidden="true" />
                    Test Stripe connection
                </Button>
                {error ? <FieldError role="alert">{error}</FieldError> : null}
            </CardContent>
        </Card>
    );
}

function SettingControl({
    field,
    value,
    error,
    disabled,
    busy,
    onChange,
    onClearSecret,
    onUpload,
}: {
    field: AdminSettingField;
    value: SettingValue | undefined;
    error?: string;
    disabled: boolean;
    busy: boolean;
    onChange: (value: SettingValue) => void;
    onClearSecret: () => void;
    onUpload: (file: File) => void;
}) {
    if (field.key === 'smtp_ehlo_domain') {
        return null;
    }
    const id = 'setting-' + field.key;
    const invalid = error !== undefined;
    const describedBy = [
        field.help ? id + '-help' : null,
        error ? id + '-error' : null,
    ]
        .filter((value): value is string => value !== null)
        .join(' ') || undefined;
    const wide = !field.secret && (['bool', 'text', 'list', 'int_list'].includes(field.type) ||
        field.key === 'homepage' || field.key === 'default_delivery');

    if (field.type === 'list' || field.type === 'int_list' ||
        field.key === 'homepage' || field.key === 'default_delivery') {
        return (
            <FieldSet
                id={id}
                tabIndex={-1}
                aria-invalid={invalid || undefined}
                aria-describedby={describedBy}
                className="min-w-0 xl:col-span-2 scroll-mb-32"
                data-invalid={invalid || undefined}
            >
                <FieldLegend>{field.label}</FieldLegend>
                {field.help ? (
                    <FieldDescription id={id + '-help'}>
                        {field.help}
                    </FieldDescription>
                ) : null}
                <ControlBody
                    field={field}
                    value={value}
                    id={id}
                    invalid={invalid}
                    describedBy={describedBy}
                    disabled={disabled}
                    busy={busy}
                    onChange={onChange}
                    onUpload={onUpload}
                />
                {error ? (
                    <FieldError id={id + '-error'}>{error}</FieldError>
                ) : null}
            </FieldSet>
        );
    }

    if (field.type === 'bool') {
        return (
            <Field className="min-w-0 xl:col-span-2" data-invalid={invalid || undefined}>
                <div className="flex min-h-11 items-start justify-between gap-4 py-2">
                    <div className="flex flex-col gap-1">
                        <FieldLabel htmlFor={id}>{field.label}</FieldLabel>
                        {field.help ? (
                            <FieldDescription id={id + '-help'}>
                                {field.help}
                            </FieldDescription>
                        ) : null}
                        {error ? (
                            <FieldError id={id + '-error'}>{error}</FieldError>
                        ) : null}
                    </div>
                    {field.control === 'checkbox' ? (
                        <Checkbox
                            id={id}
                            name={field.key}
                            checked={value === true}
                            disabled={disabled}
                            aria-invalid={invalid}
                            aria-describedby={describedBy}
                            onCheckedChange={(checked) =>
                                onChange(checked === true)
                            }
                        />
                    ) : (
                        <Switch
                            id={id}
                            name={field.key}
                            checked={value === true}
                            disabled={disabled}
                            aria-invalid={invalid}
                            aria-describedby={describedBy}
                            onCheckedChange={onChange}
                        />
                    )}
                </div>
            </Field>
        );
    }

    return (
        <Field
            className={cn('min-w-0', wide && 'xl:col-span-2')}
            data-invalid={invalid || undefined}
            data-disabled={disabled || undefined}
        >
            <div className="flex flex-wrap items-center justify-between gap-2">
                <FieldLabel htmlFor={id}>{field.label}</FieldLabel>
                <div className="flex flex-wrap items-center gap-1">
                    {field.secret ? (
                        <Badge variant="outline">
                            {field.configured ? 'Stored' : 'Not configured'}
                        </Badge>
                    ) : null}
                    {field.secret && field.configured && !disabled ? (
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            className="min-h-11 md:min-h-9"
                            disabled={busy}
                            onClick={onClearSecret}
                        >
                            <Trash2
                                data-icon="inline-start"
                                aria-hidden="true"
                            />
                            Remove
                        </Button>
                    ) : null}
                </div>
            </div>

            <ControlBody
                field={field}
                value={value}
                id={id}
                invalid={invalid}
                disabled={disabled}
                busy={busy}
                onChange={onChange}
                onUpload={onUpload}
                describedBy={describedBy}
            />

            {field.help ? (
                <FieldDescription id={id + '-help'}>
                    {field.help}
                </FieldDescription>
            ) : null}
            {field.secret && field.configured ? (
                <FieldDescription>
                    Stored — enter a replacement.
                </FieldDescription>
            ) : null}
            {error ? <FieldError id={id + '-error'}>{error}</FieldError> : null}
        </Field>
    );
}

function ControlBody({
    field,
    value,
    id,
    invalid,
    disabled,
    busy,
    onChange,
    onUpload,
    describedBy,
}: {
    field: AdminSettingField;
    value: SettingValue | undefined;
    id: string;
    invalid: boolean;
    disabled: boolean;
    busy: boolean;
    onChange: (value: SettingValue) => void;
    describedBy: string | undefined;
    onUpload: (file: File) => void;
}) {
    if (field.secret) {
        return (
            <Input
                className="min-h-11 md:min-h-10"
                id={id}
                name={field.key}
                type="password"
                autoComplete={field.autocomplete ?? 'new-password'}
                value={typeof value === 'string' ? value : ''}
                disabled={disabled}
                aria-invalid={invalid}
                aria-describedby={describedBy}
                placeholder={
                    field.configured
                        ? 'Stored — enter a replacement'
                        : (field.placeholder ?? '')
                }
                onChange={(event) => onChange(event.target.value)}
            />
        );
    }

    if (field.type === 'enum') {
        if (field.key === 'homepage' || field.key === 'default_delivery') {
            return (
                <ToggleGroup
                    type="single"
                    variant="default"
                    value={String(value ?? '')}
                    disabled={disabled}
                    aria-invalid={invalid || undefined}
                    aria-describedby={describedBy}
                    onValueChange={(next) => next !== '' && onChange(next)}
                    className="w-fit max-w-full flex-wrap gap-[3px] rounded-2xl bg-default p-[3px]"
                >
                    {Object.entries(field.options ?? {}).map(
                        ([option, label]) => (
                            <ToggleGroupItem
                                key={option}
                                value={option}
                                className="min-h-11 rounded-[13px] px-3 text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring/40 data-[state=on]:bg-card data-[state=on]:text-foreground data-[state=on]:shadow-sm md:min-h-8"
                            >
                                {label}
                            </ToggleGroupItem>
                        ),
                    )}
                </ToggleGroup>
            );
        }

        return (
            <Select
                value={String(value ?? '')}
                disabled={disabled}
                onValueChange={onChange}
            >
                <SelectTrigger
                    id={id}
                    name={field.key}
                    className="min-h-11 w-full md:min-h-10"
                    aria-invalid={invalid}
                    aria-describedby={describedBy}
                >
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    {Object.entries(field.options ?? {}).map(
                        ([option, label]) => (
                            <SelectItem key={option} value={option}>
                                {label}
                            </SelectItem>
                        ),
                    )}
                </SelectContent>
            </Select>
        );
    }

    if (field.type === 'list') {
        return (
            <div className="grid gap-2 sm:grid-cols-2">
                {Object.entries(field.options ?? {}).map(([option, label]) => {
                    const selected = Array.isArray(value)
                        ? value.map(String).includes(option)
                        : false;

                    return (
                        <label
                            key={option}
                            className="flex min-h-11 items-center gap-2 rounded-md border px-3 py-2 text-sm"
                        >
                            <Checkbox
                                name={field.key + '[]'}
                                checked={selected}
                                disabled={disabled}
                                aria-invalid={invalid || undefined}
                                aria-describedby={describedBy}
                                onCheckedChange={(checked) => {
                                    const current = Array.isArray(value)
                                        ? value.map(String)
                                        : [];
                                    onChange(
                                        checked === true
                                            ? [...current, option]
                                            : current.filter(
                                                  (item) => item !== option,
                                              ),
                                    );
                                }}
                            />
                            {label}
                        </label>
                    );
                })}
            </div>
        );
    }

    if (field.type === 'int_list' && field.control === 'number-list') {
        return (
            <NumberListField
                value={Array.isArray(value) ? value.map(Number) : []}
                disabled={disabled}
                onChange={onChange}
                describedBy={describedBy}
            />
        );
    }

    if (field.type === 'int_list') {
        return (
            <OrderedListField
                field={field}
                value={Array.isArray(value) ? value.map(Number) : []}
                disabled={disabled}
                onChange={onChange}
                describedBy={describedBy}
            />
        );
    }

    if (field.type === 'media' || optionSource(field) !== null) {
        const source = field.asset ? 'platform-assets' : optionSource(field);

        if (source !== null) {
            return (
                <div className="flex min-w-0 flex-col gap-2">
                    <SearchableCombobox
                        id={id}
                        field={field}
                        source={source}
                        value={
                            value === null || value === '' ? '' : String(value)
                        }
                        describedBy={describedBy}
                        disabled={disabled}
                        invalid={invalid}
                        onChange={(next) =>
                            onChange(
                                next === ''
                                    ? null
                                    : field.type === 'media' ||
                                        field.type === 'int_list'
                                      ? Number(next)
                                      : next,
                            )
                        }
                    />
                    {field.asset && !disabled ? (
                        <div className="group flex flex-wrap items-center gap-2">
                            <Button
                                asChild
                                variant="outline"
                                size="sm"
                                className="min-h-11 md:min-h-9"
                                disabled={busy}
                            >
                                <label
                                    htmlFor={id + '-upload'}
                                    className="cursor-pointer group-focus-within:ring-2 group-focus-within:ring-ring/40 group-focus-within:ring-offset-1"
                                >
                                    {busy ? (
                                        <Spinner data-icon="inline-start" />
                                    ) : (
                                        <Upload
                                            data-icon="inline-start"
                                            aria-hidden="true"
                                        />
                                    )}
                                    Upload asset
                                </label>
                            </Button>
                            <input
                                id={id + '-upload'}
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                disabled={busy}
                                className="sr-only"
                                onChange={(event) => {
                                    const file = event.target.files?.[0];
                                    if (file) {
                                        onUpload(file);
                                    }
                                    event.target.value = '';
                                }}
                            />
                        </div>
                    ) : null}
                </div>
            );
        }
    }

    if (field.type === 'text') {
        return (
            <Textarea
                id={id}
                name={field.key}
                rows={4}
                value={String(value ?? '')}
                disabled={disabled}
                aria-invalid={invalid}
                aria-describedby={describedBy}
                placeholder={field.placeholder ?? ''}
                onChange={(event) => onChange(event.target.value)}
            />
        );
    }

    if (field.type === 'int') {
        return (
            <Input
                className="min-h-11 md:min-h-10"
                id={id}
                name={field.key}
                type="number"
                inputMode="numeric"
                value={
                    value === null || value === undefined ? '' : String(value)
                }
                disabled={disabled}
                aria-invalid={invalid}
                aria-describedby={describedBy}
                placeholder={field.placeholder ?? ''}
                onChange={(event) =>
                    onChange(
                        event.target.value === ''
                            ? ''
                            : Number(event.target.value),
                    )
                }
            />
        );
    }

    return (
        <Input
            className="min-h-11 md:min-h-10"
            id={id}
            name={field.key}
            type={
                field.type === 'email'
                    ? 'email'
                    : field.type === 'url'
                      ? 'url'
                      : 'text'
            }
            autoComplete={field.autocomplete ?? 'off'}
            value={
                typeof value === 'string' || typeof value === 'number'
                    ? String(value)
                    : ''
            }
            disabled={disabled}
            aria-invalid={invalid}
            aria-describedby={describedBy}
            placeholder={field.placeholder ?? ''}
            onChange={(event) => onChange(event.target.value)}
        />
    );
}

function OrderedRows({
    value, disabled, onChange, label, kind,
}: {
    value: number[];
    disabled: boolean;
    onChange: (value: number[]) => void;
    label: (item: number) => string;
    kind: 'delay' | 'page';
}) {
    const move = (index: number, direction: -1 | 1) => {
        const next = [...value];
        const target = index + direction;
        if (target < 0 || target >= next.length) return;
        [next[index], next[target]] = [next[target], next[index]];
        onChange(next);
    };

    return (
        <ul className="flex flex-col gap-1">
            {value.map((item, index) => (
                <li key={`${item}-${index}`} className="flex min-w-0 items-center gap-2 rounded-md border px-2 py-1.5">
                    <span className="min-w-0 flex-1 truncate text-sm">{label(item)}</span>
                    <Button type="button" size="icon" className="size-11 md:size-9" variant="ghost"
                        disabled={disabled || index === 0} aria-label={`Move ${kind} ${index + 1} up`}
                        onClick={() => move(index, -1)}>
                        <ArrowUp aria-hidden="true" />
                    </Button>
                    <Button type="button" size="icon" className="size-11 md:size-9" variant="ghost"
                        disabled={disabled || index === value.length - 1} aria-label={`Move ${kind} ${index + 1} down`}
                        onClick={() => move(index, 1)}>
                        <ArrowDown aria-hidden="true" />
                    </Button>
                    <Button type="button" size="icon" className="size-11 md:size-9" variant="ghost"
                        disabled={disabled} aria-label={`Remove ${kind} ${index + 1}`}
                        onClick={() => onChange(value.filter((_, position) => position !== index))}>
                        <X aria-hidden="true" />
                    </Button>
                </li>
            ))}
        </ul>
    );
}

function NumberListField({ value, disabled, onChange, describedBy }: {
    value: number[];
    disabled: boolean;
    onChange: (value: number[]) => void;
    describedBy: string | undefined;
}) {
    const [input, setInput] = useState('');
    const delay = Number(input);
    const canAdd = input.trim() !== '' && Number.isInteger(delay) && delay >= 1 && delay <= 86400 && value.length < 10;

    return (
        <div className="flex min-w-0 flex-col gap-2">
            {value.length === 0 ? <p className="text-sm text-muted-foreground">No retry delays added.</p> : (
                <OrderedRows value={value} disabled={disabled} onChange={onChange} kind="delay" label={(item) => `${item} seconds`} />
            )}
            <p className="text-sm text-muted-foreground">Add up to 10 delays of 1–86,400 seconds.</p>
            {!disabled ? (
                <div className="flex flex-wrap items-center gap-2">
                    <Input type="number" aria-label="Retry delay in seconds" aria-describedby={describedBy}
                        min={1} max={86400} step={1} value={input}
                        className="min-h-11 w-full sm:w-48 md:min-h-10"
                        onChange={(event) => setInput(event.target.value)} />
                    <Button type="button" variant="outline" className="min-h-11 md:min-h-9" disabled={!canAdd}
                        onClick={() => {
                            if (!canAdd) return;
                            onChange([...value, delay]);
                            setInput('');
                        }}>
                        <Plus data-icon="inline-start" aria-hidden="true" /> Add delay
                    </Button>
                </div>
            ) : null}
        </div>
    );
}

function OrderedListField({ field, value, disabled, onChange, describedBy }: {
    field: AdminSettingField;
    value: number[];
    disabled: boolean;
    onChange: (value: number[]) => void;
    describedBy: string | undefined;
}) {
    const source = optionSource(field) ?? 'published-pages';
    const selectedIds = value.join(',');
    const [labels, setLabels] = useState<Record<string, string>>({});

    useEffect(() => {
        if (selectedIds === '') {
            setLabels({});
            return;
        }
        const controller = new AbortController();
        fetch(optionUrl(source, '', selectedIds.split(',')), {
            headers: { Accept: 'application/json' },
            signal: controller.signal,
            credentials: 'same-origin',
        })
            .then((response) => {
                if (!response.ok) throw new Error('Unable to load page titles');
                return response.json() as Promise<{ items?: OptionItem[] }>;
            })
            .then((payload) => {
                if (!controller.signal.aborted) {
                    setLabels(Object.fromEntries((payload.items ?? []).map((item) => [item.value, item.label])));
                }
            })
            .catch(() => {
                if (!controller.signal.aborted) setLabels({});
            });
        return () => controller.abort();
    }, [source, selectedIds]);

    return (
        <div className="flex min-w-0 flex-col gap-2">
            <OrderedRows value={value} disabled={disabled} onChange={onChange} kind="page"
                label={(item) => labels[String(item)] ?? `Page #${item}`} />
            {!disabled ? (
                <SearchableCombobox
                    id={'setting-' + field.key + '-add'}
                    field={field}
                    source={source}
                    value=""
                    disabled={disabled}
                    invalid={false}
                    placeholder="Add an item…"
                    describedBy={describedBy}
                    onChange={(next) => {
                        const numeric = Number(next);
                        if (Number.isInteger(numeric) && !value.includes(numeric)) {
                            onChange([...value, numeric]);
                        }
                    }}
                />
            ) : null}
        </div>
    );
}

function SearchableCombobox({
    id,
    field,
    source,
    value,
    disabled,
    invalid,
    placeholder,
    onChange,
    describedBy,
}: {
    id: string;
    field: AdminSettingField;
    source: string;
    value: string;
    disabled: boolean;
    invalid: boolean;
    placeholder?: string;
    onChange: (value: string) => void;
    describedBy: string | undefined;
}) {
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [items, setItems] = useState<OptionItem[]>([]);

    useEffect(() => {
        const controller = new AbortController();
        const timer = window.setTimeout(() => {
            fetch(optionUrl(source, query, value ? [value] : []), {
                headers: { Accept: 'application/json' },
                signal: controller.signal,
                credentials: 'same-origin',
            })
                .then((response) =>
                    response.ok ? response.json() : { items: [] },
                )
                .then((payload: { items?: OptionItem[] }) =>
                    setItems(payload.items ?? []),
                )
                .catch(() => undefined);
        }, 150);

        return () => {
            controller.abort();
            window.clearTimeout(timer);
        };
    }, [query, source, value]);

    const selected = items.find((item) => item.value === value);

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button
                    id={id}
                    type="button"
                    variant="outline"
                    role="combobox"
                    aria-label={field.type === 'int_list' ? `Add page to ${field.label}` : undefined}
                    aria-expanded={open}
                    aria-invalid={invalid}
                    disabled={disabled}
                    aria-describedby={describedBy}
                    className="min-h-11 w-full justify-between font-normal md:min-h-10"
                >
                    <span className="truncate">
                        {selected?.label ??
                            (value === '' ? (placeholder ?? 'Select…') : value)}
                    </span>
                    <ChevronsUpDown className="opacity-50" aria-hidden="true" />
                </Button>
            </PopoverTrigger>
            <PopoverContent
                align="start"
                className="w-[min(24rem,calc(100vw-2rem))] p-0"
            >
                <Command shouldFilter={false}>
                    <CommandInput
                        aria-label={`Search ${field.label}`}
                        placeholder={placeholder ?? 'Search…'}
                        value={query}
                        onValueChange={setQuery}
                    />
                    <CommandList>
                        <CommandEmpty>No results.</CommandEmpty>
                        <CommandGroup>
                            {value !== '' && field.type !== 'int_list' ? (
                                <CommandItem
                                    value="__clear"
                                    onSelect={() => {
                                        onChange('');
                                        setOpen(false);
                                    }}
                                >
                                    Clear selection
                                </CommandItem>
                            ) : null}
                            {items.map((item) => (
                                <CommandItem
                                    key={item.value}
                                    value={item.value + ' ' + item.label}
                                    onSelect={() => {
                                        onChange(item.value);
                                        setOpen(false);
                                    }}
                                >
                                    <Check
                                        className={cn(
                                            'size-4',
                                            item.value === value
                                                ? 'opacity-100'
                                                : 'opacity-0',
                                        )}
                                        aria-hidden="true"
                                    />
                                    <span className="flex min-w-0 flex-col">
                                        <span className="truncate">
                                            {item.label}
                                        </span>
                                        {item.description ? (
                                            <span className="truncate text-xs text-muted-foreground">
                                                {item.description}
                                            </span>
                                        ) : null}
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
