import { Head, useForm } from '@inertiajs/react';
import { Save, ShieldCheck } from 'lucide-react';
import { useState } from 'react';
import SettingsLayout from '@/pages/admin/settings/layout';
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
import settings from '@/routes/admin/settings';
import type {
    AdminSettingField,
    AdminSettingsPageProps,
} from '@/types/admin-settings';

type SettingValue = string | number | boolean | string[] | number[] | null;
type FormValues = Record<string, SettingValue>;

function initialValues(fields: AdminSettingField[]): FormValues {
    return Object.fromEntries(
        fields.map((field) => [
            field.key,
            field.secret
                ? ''
                : field.type === 'int_list'
                  ? ((field.value as number[] | null)?.join(', ') ?? '')
                  : (field.value ?? ''),
        ]),
    );
}

export default function AdminSettingsSection({
    section,
    navigation,
}: AdminSettingsPageProps) {
    const [saved, setSaved] = useState(false);
    const form = useForm<FormValues>(initialValues(section.fields));

    function save() {
        setSaved(false);
        form.transform((values) => ({
            version: section.version,
            values: Object.fromEntries(
                section.fields.map((field) => {
                    let value = values[field.key];

                    if (field.type === 'int_list') {
                        value = String(value ?? '')
                            .split(',')
                            .map((item) => item.trim())
                            .filter((item) => item !== '')
                            .map((item) => Number(item));
                    } else if (field.type === 'media') {
                        value =
                            value === '' || value === null
                                ? null
                                : Number(value);
                    } else if (field.type === 'int') {
                        value = Number(value);
                    }

                    return [field.key, value];
                }),
            ),
        }));

        form.patch(settings.update(section.key).url, {
            preserveScroll: true,
            onSuccess: () => setSaved(true),
        });
    }

    return (
        <>
            <Head title={`${section.label} settings — Administration`} />
            <SettingsLayout section={section.key} navigation={navigation}>
                <Card>
                    <CardHeader className="gap-1">
                        <div className="flex flex-wrap items-start justify-between gap-2">
                            <div>
                                <CardTitle>{section.label}</CardTitle>
                                <CardDescription>
                                    {section.description}
                                </CardDescription>
                            </div>
                            <Badge variant="outline">
                                Version {section.version}
                            </Badge>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <form
                            className="grid gap-6"
                            onSubmit={(event) => {
                                event.preventDefault();
                                save();
                            }}
                        >
                            <FieldGroup>
                                {section.fields.map((field) => (
                                    <AdminSettingControl
                                        key={field.key}
                                        field={field}
                                        value={form.data[field.key]}
                                        error={
                                            form.errors[`values.${field.key}`]
                                        }
                                        onChange={(value) =>
                                            form.setData(field.key, value)
                                        }
                                    />
                                ))}
                            </FieldGroup>

                            {form.errors.version && (
                                <FieldError>{form.errors.version}</FieldError>
                            )}
                            {saved && (
                                <p
                                    role="status"
                                    className="text-sm text-emerald-600"
                                >
                                    Settings saved.
                                </p>
                            )}

                            <div className="flex flex-wrap items-center gap-3">
                                <Button
                                    type="submit"
                                    className="min-h-11"
                                    disabled={
                                        form.processing ||
                                        section.fields.every(
                                            (field) => field.readOnly,
                                        )
                                    }
                                >
                                    <Save aria-hidden="true" />
                                    {form.processing ? 'Saving…' : 'Save'}
                                </Button>
                                {section.fields.some(
                                    (field) => field.source === 'environment',
                                ) && (
                                    <span className="inline-flex items-center gap-1 text-xs text-muted-foreground">
                                        <ShieldCheck
                                            className="size-3"
                                            aria-hidden="true"
                                        />
                                        Environment values are read-only and
                                        always win.
                                    </span>
                                )}
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </SettingsLayout>
        </>
    );
}

function AdminSettingControl({
    field,
    value,
    error,
    onChange,
}: {
    field: AdminSettingField;
    value: SettingValue | undefined;
    error?: string;
    onChange: (value: SettingValue) => void;
}) {
    const id = `setting-${field.key}`;
    const disabled = field.readOnly;

    if (field.type === 'bool') {
        return (
            <Field>
                <div className="flex items-start gap-3">
                    <Checkbox
                        id={id}
                        checked={value === true}
                        disabled={disabled}
                        onCheckedChange={(checked) =>
                            onChange(checked === true)
                        }
                    />
                    <div className="grid gap-1">
                        <FieldLabel htmlFor={id}>{field.label}</FieldLabel>
                        {field.help && (
                            <FieldDescription>{field.help}</FieldDescription>
                        )}
                        {field.source === 'environment' && (
                            <Badge variant="outline" className="w-fit">
                                Environment
                            </Badge>
                        )}
                    </div>
                </div>
                {error && <FieldError>{error}</FieldError>}
            </Field>
        );
    }

    return (
        <Field>
            <div className="flex flex-wrap items-center justify-between gap-2">
                <FieldLabel htmlFor={id}>{field.label}</FieldLabel>
                <div className="flex gap-1">
                    {field.secret && (
                        <Badge variant="outline">
                            {field.configured ? 'Configured' : 'Not configured'}
                        </Badge>
                    )}
                    {field.source === 'environment' && (
                        <Badge variant="outline">Environment</Badge>
                    )}
                </div>
            </div>

            {field.type === 'enum' && (
                <Select
                    value={String(value ?? '')}
                    disabled={disabled}
                    onValueChange={onChange}
                >
                    <SelectTrigger id={id} className="min-h-11 w-full">
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
            )}

            {field.type === 'text' && (
                <Textarea
                    id={id}
                    rows={3}
                    value={String(value ?? '')}
                    disabled={disabled}
                    onChange={(event) => onChange(event.target.value)}
                />
            )}

            {field.type === 'list' && (
                <div className="grid gap-2 sm:grid-cols-2">
                    {Object.entries(field.options ?? {}).map(
                        ([option, label]) => {
                            const selected = Array.isArray(value)
                                ? value.map(String).includes(option)
                                : false;

                            return (
                                <label
                                    key={option}
                                    className="flex items-center gap-2 text-sm"
                                >
                                    <Checkbox
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
                                                          (item) =>
                                                              item !== option,
                                                      ),
                                            );
                                        }}
                                    />
                                    {label}
                                </label>
                            );
                        },
                    )}
                </div>
            )}

            {field.type === 'int_list' && (
                <Input
                    id={id}
                    value={String(value ?? '')}
                    disabled={disabled}
                    placeholder="1, 2, 3"
                    onChange={(event) => onChange(event.target.value)}
                />
            )}

            {(field.type === 'string' ||
                field.type === 'email' ||
                field.type === 'url' ||
                field.type === 'timezone') && (
                <Input
                    id={id}
                    type={
                        field.type === 'email'
                            ? 'email'
                            : field.type === 'url'
                              ? 'url'
                              : 'text'
                    }
                    value={String(value ?? '')}
                    disabled={disabled}
                    onChange={(event) => onChange(event.target.value)}
                />
            )}

            {(field.type === 'int' || field.type === 'media') && (
                <Input
                    id={id}
                    type="number"
                    min={0}
                    value={String(value ?? '')}
                    disabled={disabled}
                    onChange={(event) => onChange(event.target.value)}
                />
            )}

            {field.help && <FieldDescription>{field.help}</FieldDescription>}
            {field.secret && !field.readOnly && (
                <FieldDescription>
                    Leave blank to preserve the existing secret.
                </FieldDescription>
            )}
            {error && <FieldError>{error}</FieldError>}
        </Field>
    );
}
