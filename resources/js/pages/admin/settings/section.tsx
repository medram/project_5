import { Head, router, usePage } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowUp,
    Check,
    ChevronsUpDown,
    Copy,
    Save,
    Trash2,
    Upload,
    X,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import SettingsLayout from '@/pages/admin/settings/layout';
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

type SettingValue = string | number | boolean | string[] | number[] | null;
type FormValues = Record<string, SettingValue>;
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
        [section.version],
    );
    const [values, setValues] = useState<FormValues>(initial);
    const [processing, setProcessing] = useState(false);
    const [uploading, setUploading] = useState<string | null>(null);
    const page = usePage<{ errors: Record<string, string> }>();
    const errors = page.props.errors ?? {};

    useEffect(() => {
        setValues(initialValues(fields));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [section.version]);

    const dirty = JSON.stringify(values) !== JSON.stringify(initial);

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

        requestAnimationFrame(() => {
            const invalid = document.querySelector<HTMLElement>(
                '[aria-invalid="true"]',
            );
            invalid?.focus();
            invalid?.scrollIntoView({ block: 'center', behavior: 'smooth' });
        });
    }, [errors]);

    const visible = (field: AdminSettingField): boolean =>
        field.visibleWhen === null ||
        Object.entries(field.visibleWhen).every(
            ([key, expected]) => values[key] === expected,
        );

    const setValue = (key: string, value: SettingValue) => {
        setValues((current) => ({ ...current, [key]: value }));
    };

    const save = () => {
        const changed: FormValues = {};

        for (const field of fields) {
            if (!visible(field)) {
                continue;
            }

            const value = values[field.key];

            if (
                field.secret &&
                (typeof value !== 'string' || value.trim() === '')
            ) {
                continue;
            }

            if (JSON.stringify(value) === JSON.stringify(initial[field.key])) {
                continue;
            }

            changed[field.key] = value;
        }

        if (Object.keys(changed).length === 0) {
            return;
        }

        setProcessing(true);
        router.patch(
            settings.update(section.key).url,
            { version: section.version, values: changed },
            {
                preserveScroll: true,
                onFinish: () => setProcessing(false),
            },
        );
    };

    const clearSecret = (field: AdminSettingField) => {
        if (
            !window.confirm(
                'Remove the stored ' + field.label.toLowerCase() + '?',
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
                <div className="flex min-w-0 flex-col gap-5 pb-24">
                    {section.description ? (
                        <p className="max-w-3xl text-sm text-muted-foreground">
                            {section.description}
                        </p>
                    ) : null}

                    {section.readiness ? (
                        <Alert
                            variant={
                                section.readiness.state === 'incomplete'
                                    ? 'destructive'
                                    : 'default'
                            }
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

                    {Object.keys(errors).length > 0 ? (
                        <Alert variant="destructive">
                            <AlertTitle>Settings were not saved</AlertTitle>
                            <AlertDescription>
                                Fix the highlighted fields and save again.
                            </AlertDescription>
                        </Alert>
                    ) : null}

                    {section.groups.map((group) => (
                        <Card key={group.key} className="min-w-0">
                            <CardHeader className="gap-1">
                                <CardTitle>{group.label}</CardTitle>
                                {group.description ? (
                                    <CardDescription>
                                        {group.description}
                                    </CardDescription>
                                ) : null}
                            </CardHeader>
                            <CardContent>
                                <FieldGroup>
                                    {group.fields
                                        .filter(visible)
                                        .map((field) => (
                                            <SettingControl
                                                key={field.key}
                                                field={field}
                                                value={values[field.key]}
                                                error={
                                                    errors[
                                                        'values.' + field.key
                                                    ]
                                                }
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
                        <TestEmailPanel error={errors.email} />
                    ) : null}
                    {section.key === 'subscriptions' && canManage ? (
                        <TestStripePanel error={errors.stripe} />
                    ) : null}

                    {canManage ? (
                        <div className="sticky bottom-0 z-20 -mx-4 mt-1 border-t bg-background/95 px-4 py-3 backdrop-blur supports-[backdrop-filter]:bg-background/80 md:-mx-6 md:px-6">
                            <div className="flex flex-wrap items-center gap-3">
                                <Button
                                    type="button"
                                    className="min-h-11"
                                    disabled={!dirty || processing}
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
                                    {dirty
                                        ? 'Unsaved changes'
                                        : 'All changes saved'}
                                </p>
                            </div>
                        </div>
                    ) : (
                        <Alert>
                            <AlertTitle>View-only access</AlertTitle>
                            <AlertDescription>
                                You can inspect settings and readiness. Changing
                                values requires the manage permission.
                            </AlertDescription>
                        </Alert>
                    )}
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
                <CardTitle>Google OAuth callback URL</CardTitle>
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

function TestEmailPanel({ error }: { error?: string }) {
    const [recipient, setRecipient] = useState('');

    return (
        <Card>
            <CardHeader>
                <CardTitle>Test email delivery</CardTitle>
                <CardDescription>
                    Sends one plain message using the saved configuration. Save
                    changes before testing.
                </CardDescription>
            </CardHeader>
            <CardContent className="flex flex-col gap-3">
                <Field data-invalid={Boolean(error)}>
                    <FieldLabel htmlFor="test-email-recipient">
                        Recipient
                    </FieldLabel>
                    <Input
                        id="test-email-recipient"
                        name="recipient"
                        type="email"
                        autoComplete="email"
                        value={recipient}
                        aria-invalid={Boolean(error)}
                        onChange={(event) => setRecipient(event.target.value)}
                    />
                    {error ? <FieldError>{error}</FieldError> : null}
                </Field>
                <Button
                    type="button"
                    variant="outline"
                    disabled={recipient === ''}
                    onClick={() =>
                        router.post(
                            '/admin/settings/email/test',
                            { recipient },
                            { preserveScroll: true },
                        )
                    }
                >
                    Send test email
                </Button>
            </CardContent>
        </Card>
    );
}

function TestStripePanel({ error }: { error?: string }) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>Validate Stripe credentials</CardTitle>
                <CardDescription>
                    Performs a read-only account request with the saved
                    settings. No checkout or subscription is created.
                </CardDescription>
            </CardHeader>
            <CardContent className="flex flex-col gap-3">
                <Button
                    type="button"
                    variant="outline"
                    onClick={() =>
                        router.post(
                            '/admin/settings/subscriptions/test',
                            {},
                            { preserveScroll: true },
                        )
                    }
                >
                    Test Stripe connection
                </Button>
                {error ? <FieldError>{error}</FieldError> : null}
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
    const id = 'setting-' + field.key;
    const invalid = error !== undefined;
    const describedBy = field.help ? id + '-help' : undefined;

    if (field.type === 'bool') {
        return (
            <Field data-invalid={invalid || undefined}>
                <div className="flex items-start justify-between gap-4 rounded-md border p-3">
                    <div className="flex flex-col gap-1">
                        <FieldLabel htmlFor={id}>{field.label}</FieldLabel>
                        {field.help ? (
                            <FieldDescription id={id + '-help'}>
                                {field.help}
                            </FieldDescription>
                        ) : null}
                        {error ? <FieldError>{error}</FieldError> : null}
                    </div>
                    {field.control === 'checkbox' ? (
                        <Checkbox
                            id={id}
                            name={field.key}
                            checked={value === true}
                            disabled={disabled}
                            aria-invalid={invalid}
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
                            onCheckedChange={onChange}
                        />
                    )}
                </div>
            </Field>
        );
    }

    return (
        <Field
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
            />

            {field.help ? (
                <FieldDescription id={describedBy}>
                    {field.help}
                </FieldDescription>
            ) : null}
            {field.secret && field.configured ? (
                <FieldDescription>
                    Stored — enter a replacement.
                </FieldDescription>
            ) : null}
            {error ? <FieldError>{error}</FieldError> : null}
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
}: {
    field: AdminSettingField;
    value: SettingValue | undefined;
    id: string;
    invalid: boolean;
    disabled: boolean;
    busy: boolean;
    onChange: (value: SettingValue) => void;
    onUpload: (file: File) => void;
}) {
    if (field.secret) {
        return (
            <Input
                id={id}
                name={field.key}
                type="password"
                autoComplete={field.autocomplete ?? 'new-password'}
                value={typeof value === 'string' ? value : ''}
                disabled={disabled}
                aria-invalid={invalid}
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
                    variant="outline"
                    value={String(value ?? '')}
                    disabled={disabled}
                    onValueChange={(next) => next !== '' && onChange(next)}
                    className="flex-wrap"
                >
                    {Object.entries(field.options ?? {}).map(
                        ([option, label]) => (
                            <ToggleGroupItem key={option} value={option}>
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
                    className="min-h-11 w-full"
                    aria-invalid={invalid}
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
                            className="flex items-center gap-2 rounded-md border px-3 py-2 text-sm"
                        >
                            <Checkbox
                                name={field.key + '[]'}
                                checked={selected}
                                disabled={disabled}
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

    if (field.type === 'int_list') {
        return (
            <OrderedListField
                field={field}
                value={Array.isArray(value) ? value.map(Number) : []}
                disabled={disabled}
                onChange={onChange}
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
                        <div className="flex items-center gap-2">
                            <Button
                                asChild
                                variant="outline"
                                size="sm"
                                disabled={busy}
                            >
                                <label
                                    htmlFor={id + '-upload'}
                                    className="cursor-pointer"
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
                placeholder={field.placeholder ?? ''}
                onChange={(event) => onChange(event.target.value)}
            />
        );
    }

    if (field.type === 'int') {
        return (
            <Input
                id={id}
                name={field.key}
                type="number"
                inputMode="numeric"
                value={
                    value === null || value === undefined ? '' : String(value)
                }
                disabled={disabled}
                aria-invalid={invalid}
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
            placeholder={field.placeholder ?? ''}
            onChange={(event) => onChange(event.target.value)}
        />
    );
}

function OrderedListField({
    field,
    value,
    disabled,
    onChange,
}: {
    field: AdminSettingField;
    value: number[];
    disabled: boolean;
    onChange: (value: number[]) => void;
}) {
    const source = optionSource(field) ?? 'published-pages';
    const move = (index: number, direction: -1 | 1) => {
        const next = [...value];
        const target = index + direction;
        if (target < 0 || target >= next.length) {
            return;
        }
        [next[index], next[target]] = [next[target], next[index]];
        onChange(next);
    };

    return (
        <div className="flex flex-col gap-2">
            <ul className="flex flex-col gap-1">
                {value.map((item, index) => (
                    <li
                        key={String(item) + '-' + index}
                        className="flex items-center gap-2 rounded-md border px-2 py-1.5"
                    >
                        <span className="min-w-0 flex-1 truncate text-sm">
                            {String(item)}
                        </span>
                        <Button
                            type="button"
                            size="icon"
                            variant="ghost"
                            disabled={disabled || index === 0}
                            aria-label="Move up"
                            onClick={() => move(index, -1)}
                        >
                            <ArrowUp aria-hidden="true" />
                        </Button>
                        <Button
                            type="button"
                            size="icon"
                            variant="ghost"
                            disabled={disabled || index === value.length - 1}
                            aria-label="Move down"
                            onClick={() => move(index, 1)}
                        >
                            <ArrowDown aria-hidden="true" />
                        </Button>
                        <Button
                            type="button"
                            size="icon"
                            variant="ghost"
                            disabled={disabled}
                            aria-label="Remove"
                            onClick={() =>
                                onChange(
                                    value.filter(
                                        (_, position) => position !== index,
                                    ),
                                )
                            }
                        >
                            <X aria-hidden="true" />
                        </Button>
                    </li>
                ))}
            </ul>
            {!disabled ? (
                <SearchableCombobox
                    id={'setting-' + field.key + '-add'}
                    field={field}
                    source={source}
                    value=""
                    disabled={disabled}
                    invalid={false}
                    placeholder="Add an item…"
                    onChange={(next) => {
                        const numeric = Number(next);
                        if (
                            Number.isInteger(numeric) &&
                            !value.includes(numeric)
                        ) {
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
}: {
    id: string;
    field: AdminSettingField;
    source: string;
    value: string;
    disabled: boolean;
    invalid: boolean;
    placeholder?: string;
    onChange: (value: string) => void;
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
                    aria-expanded={open}
                    aria-invalid={invalid}
                    disabled={disabled}
                    className="min-h-11 w-full justify-between font-normal"
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
