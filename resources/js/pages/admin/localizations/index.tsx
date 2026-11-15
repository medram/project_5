import { Head, Link, router, useForm } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';
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
import { Field, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import localizations from '@/routes/admin/localizations';
import type {
    AdminLocalizationsPageProps,
    AdminTranslation,
} from '@/types/localization';

export default function AdminLocalizationsIndex({
    locales,
    selected,
    translations,
    filters,
}: AdminLocalizationsPageProps) {
    const [query, setQuery] = useState(filters.q);
    const [editing, setEditing] = useState<AdminTranslation | null>(null);
    const createForm = useForm({
        code: '',
        name: '',
        direction: 'ltr',
        is_published: false,
    });
    const importForm = useForm<{ file: File | null }>({ file: null });
    const active = locales.find((locale) => locale.code === selected);

    function search(event: FormEvent) {
        event.preventDefault();
        router.get(
            localizations.index().url,
            { locale: selected ?? undefined, q: query || undefined },
            { preserveScroll: true },
        );
    }

    function create(event: FormEvent) {
        event.preventDefault();
        createForm.post(localizations.store().url, {
            preserveScroll: true,
            onSuccess: () => createForm.reset(),
        });
    }

    function importJson(event: FormEvent) {
        event.preventDefault();
        if (!active || !importForm.data.file) return;
        importForm.post(localizations.import(active.code).url, {
            preserveScroll: true,
            forceFormData: true,
        });
    }

    return (
        <>
            <Head title="Localizations — Administration" />
            <div className="mx-auto flex w-full max-w-7xl min-w-0 flex-1 flex-col gap-5 px-5 pt-4 pb-10">
                <header>
                    <h1 className="text-2xl leading-8 font-semibold tracking-[-0.02em]">
                        Database localizations
                    </h1>
                    <p className="max-w-3xl text-sm text-muted-foreground">
                        Flat JSON string maps only. English is seeded,
                        published, and the universal fallback.
                    </p>
                </header>

                <div className="grid gap-4 xl:grid-cols-[300px_minmax(0,1fr)]">
                    <div className="grid gap-4">
                        <Card>
                            <CardHeader>
                                <CardTitle>Locales</CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-2">
                                {locales.map((locale) => (
                                    <Link
                                        key={locale.code}
                                        href={
                                            localizations.index({
                                                query: { locale: locale.code },
                                            }).url
                                        }
                                        className={`rounded-md border px-3 py-2 text-sm ${locale.code === selected ? 'bg-muted font-medium' : 'hover:bg-muted/60'}`}
                                    >
                                        <span className="flex items-center justify-between">
                                            <span>{locale.name}</span>
                                            <span className="flex gap-1">
                                                {locale.isDefault && (
                                                    <Badge variant="secondary">
                                                        Default
                                                    </Badge>
                                                )}
                                                <Badge variant="outline">
                                                    {locale.direction}
                                                </Badge>
                                            </span>
                                        </span>
                                        <span className="text-xs text-muted-foreground">
                                            {locale.code} ·{' '}
                                            {locale.translationsCount}{' '}
                                            translations
                                        </span>
                                    </Link>
                                ))}
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader>
                                <CardTitle>Add locale</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <form className="grid gap-2" onSubmit={create}>
                                    <FieldGroup className="gap-2">
                                        <Field>
                                            <FieldLabel
                                                className="sr-only"
                                                htmlFor="new-locale-code"
                                            >
                                                Locale code
                                            </FieldLabel>
                                            <Input
                                                id="new-locale-code"
                                                placeholder="fr or ar"
                                                value={createForm.data.code}
                                                onChange={(e) =>
                                                    createForm.setData(
                                                        'code',
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                        </Field>
                                        <Field>
                                            <FieldLabel
                                                className="sr-only"
                                                htmlFor="new-locale-name"
                                            >
                                                Locale name
                                            </FieldLabel>
                                            <Input
                                                id="new-locale-name"
                                                placeholder="French"
                                                value={createForm.data.name}
                                                onChange={(e) =>
                                                    createForm.setData(
                                                        'name',
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                        </Field>
                                        <Field>
                                            <FieldLabel
                                                className="sr-only"
                                                htmlFor="new-locale-direction"
                                            >
                                                Text direction
                                            </FieldLabel>
                                            <Select
                                                value={
                                                    createForm.data.direction
                                                }
                                                onValueChange={(v) =>
                                                    createForm.setData(
                                                        'direction',
                                                        v,
                                                    )
                                                }
                                            >
                                                <SelectTrigger id="new-locale-direction">
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="ltr">
                                                        LTR
                                                    </SelectItem>
                                                    <SelectItem value="rtl">
                                                        RTL
                                                    </SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </Field>
                                    </FieldGroup>
                                    <Button
                                        type="submit"
                                        disabled={createForm.processing}
                                    >
                                        Add locale
                                    </Button>
                                </form>
                            </CardContent>
                        </Card>
                    </div>
                    <div className="grid gap-4">
                        {active && <LocaleEditor locale={active} />}
                        <Card>
                            <CardHeader className="gap-2">
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <div>
                                        <CardTitle>Translations</CardTitle>
                                        <CardDescription>
                                            {active?.code ?? 'No locale'} ·
                                            English fallback is automatic.
                                        </CardDescription>
                                    </div>
                                    {active && (
                                        <Button
                                            asChild
                                            variant="outline"
                                            size="sm"
                                        >
                                            <a
                                                href={
                                                    localizations.export(
                                                        active.code,
                                                    ).url
                                                }
                                            >
                                                Export JSON
                                            </a>
                                        </Button>
                                    )}
                                </div>
                                <form onSubmit={search}>
                                    <FieldGroup className="flex-row items-end gap-2">
                                        <Field className="min-w-0 flex-1">
                                            <FieldLabel
                                                className="sr-only"
                                                htmlFor="translation-search"
                                            >
                                                Search translations
                                            </FieldLabel>
                                            <Input
                                                id="translation-search"
                                                value={query}
                                                placeholder="Search keys or values"
                                                onChange={(e) =>
                                                    setQuery(e.target.value)
                                                }
                                            />
                                        </Field>
                                        <Button type="submit" variant="outline">
                                            Search
                                        </Button>
                                    </FieldGroup>
                                </form>
                            </CardHeader>
                            <CardContent className="grid gap-4">
                                <div className="overflow-hidden rounded-md border">
                                    <table
                                        data-table-surface={
                                            translations.data.length > 0
                                                ? 'populated'
                                                : undefined
                                        }
                                        className="w-full text-sm"
                                    >
                                        <thead className="bg-muted/40 text-left text-xs text-muted-foreground">
                                            <tr>
                                                <th className="px-3 py-2">
                                                    Key
                                                </th>
                                                <th className="px-3 py-2">
                                                    Value
                                                </th>
                                                <th className="px-3 py-2 text-right">
                                                    Edit
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {translations.data.map((row) => (
                                                <tr
                                                    key={row.key}
                                                    className="border-t"
                                                >
                                                    <td className="max-w-64 truncate px-3 py-2 font-mono text-xs">
                                                        {row.key}
                                                    </td>
                                                    <td className="max-w-80 truncate px-3 py-2">
                                                        {row.value}
                                                    </td>
                                                    <td className="px-3 py-2 text-right">
                                                        <Button
                                                            size="sm"
                                                            variant="ghost"
                                                            type="button"
                                                            onClick={() =>
                                                                setEditing(row)
                                                            }
                                                        >
                                                            Edit
                                                        </Button>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                                <form
                                    className="flex flex-col gap-2 rounded-md border p-3 sm:flex-row sm:items-end"
                                    onSubmit={importJson}
                                >
                                    <div className="grid flex-1 gap-1">
                                        <Label htmlFor="locale-import">
                                            Import flat JSON
                                        </Label>
                                        <Input
                                            id="locale-import"
                                            type="file"
                                            accept="application/json,.json"
                                            onChange={(e) =>
                                                importForm.setData(
                                                    'file',
                                                    e.target.files?.[0] ?? null,
                                                )
                                            }
                                        />
                                    </div>
                                    <Button
                                        type="submit"
                                        variant="outline"
                                        disabled={
                                            importForm.processing ||
                                            !importForm.data.file
                                        }
                                    >
                                        Import
                                    </Button>
                                </form>
                                {Object.values(importForm.errors)[0] && (
                                    <p className="text-sm text-destructive">
                                        {Object.values(importForm.errors)[0]}
                                    </p>
                                )}
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
            {editing && active && (
                <TranslationDialog
                    locale={active}
                    translation={editing}
                    onClose={() => setEditing(null)}
                />
            )}
        </>
    );
}

function LocaleEditor({
    locale,
}: {
    locale: AdminLocalizationsPageProps['locales'][number];
}) {
    const form = useForm({
        name: locale.name,
        direction: locale.direction,
        is_published: locale.isPublished,
        is_default: locale.isDefault,
    });
    return (
        <Card>
            <CardHeader>
                <CardTitle>{locale.name}</CardTitle>
                <CardDescription>
                    English cannot be unpublished or deleted.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <form
                    className="grid gap-3 sm:grid-cols-2"
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.patch(localizations.update(locale.code).url, {
                            preserveScroll: true,
                        });
                    }}
                >
                    <FieldGroup className="grid gap-3 sm:col-span-2 sm:grid-cols-2">
                        <Field className="gap-1">
                            <FieldLabel htmlFor="locale-name">Name</FieldLabel>
                            <Input
                                id="locale-name"
                                value={form.data.name}
                                onChange={(e) =>
                                    form.setData('name', e.target.value)
                                }
                            />
                        </Field>
                        <Field className="gap-1">
                            <FieldLabel htmlFor="locale-direction">
                                Direction
                            </FieldLabel>
                            <Select
                                value={form.data.direction}
                                onValueChange={(v) =>
                                    form.setData('direction', v)
                                }
                            >
                                <SelectTrigger id="locale-direction">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="ltr">LTR</SelectItem>
                                    <SelectItem value="rtl">RTL</SelectItem>
                                </SelectContent>
                            </Select>
                        </Field>
                        <Field className="flex-row items-center gap-2">
                            <Checkbox
                                id="locale-is-published"
                                checked={form.data.is_published}
                                onCheckedChange={(checked) =>
                                    form.setData(
                                        'is_published',
                                        checked === true,
                                    )
                                }
                            />
                            <FieldLabel
                                className="text-sm font-normal"
                                htmlFor="locale-is-published"
                            >
                                Published
                            </FieldLabel>
                        </Field>
                        <Field className="flex-row items-center gap-2">
                            <Checkbox
                                id="locale-is-default"
                                checked={form.data.is_default}
                                onCheckedChange={(checked) =>
                                    form.setData('is_default', checked === true)
                                }
                            />
                            <FieldLabel
                                className="text-sm font-normal"
                                htmlFor="locale-is-default"
                            >
                                Default locale
                            </FieldLabel>
                        </Field>
                    </FieldGroup>
                    <Button
                        type="submit"
                        className="sm:col-span-2"
                        disabled={form.processing}
                    >
                        Save locale
                    </Button>
                </form>
            </CardContent>
        </Card>
    );
}

function TranslationDialog({
    locale,
    translation,
    onClose,
}: {
    locale: AdminLocalizationsPageProps['locales'][number];
    translation: AdminTranslation;
    onClose: () => void;
}) {
    const form = useForm({ key: translation.key, value: translation.value });
    return (
        <div
            className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
            role="dialog"
            aria-modal="true"
        >
            <div className="w-full max-w-lg rounded-2xl border border-border bg-card p-5 shadow-xl">
                <h2 className="font-semibold">Edit translation</h2>
                <p className="mb-3 text-sm text-muted-foreground">
                    {locale.name}
                </p>
                <form
                    className="grid gap-3"
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.put(
                            localizations.translations.update(locale.code).url,
                            { preserveScroll: true, onSuccess: onClose },
                        );
                    }}
                >
                    <FieldGroup className="gap-3">
                        <Field className="gap-1">
                            <FieldLabel htmlFor="translation-key">
                                Key
                            </FieldLabel>
                            <Input
                                id="translation-key"
                                value={form.data.key}
                                readOnly
                            />
                        </Field>
                        <Field className="gap-1">
                            <FieldLabel htmlFor="translation-value">
                                Value
                            </FieldLabel>
                            <Textarea
                                id="translation-value"
                                rows={5}
                                value={form.data.value}
                                onChange={(e) =>
                                    form.setData('value', e.target.value)
                                }
                            />
                        </Field>
                    </FieldGroup>
                    <div className="flex justify-end gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onClose}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            Save
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    );
}
