# 11 — Recipes

Read this when you need a copy-paste skeleton for a page, list, form, dialog,
confirm, KPI row, chart, settings section, filter toolbar, table/cards block,
empty state, or status badge.

Copy these skeletons, then trim them to the surface. Every recipe is derived
from a real implementation; the `source` comment names it. Replace domain
types and fields, not the API shape.

## 1. Standard page

```tsx
// source: resources/js/pages/folders/index.tsx (structure)
import { Head } from '@inertiajs/react';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import type { FolderIndexProps } from '@/types/folders';
import { index as foldersIndex } from '@/routes/folders';

export default function Folders({ folders }: FolderIndexProps) {
    return (
        <>
            <Head title="Folders" />
            <div className="mx-auto flex w-full max-w-7xl min-w-0 flex-1 flex-col gap-5 px-5 pt-4 pb-10">
                <PageHeader
                    title="Folders"
                    description="Group related links into collections."
                    actions={<Button>Create folder</Button>}
                />
                {/* Render folders.data here. */}
            </div>
        </>
    );
}

Folders.layout = {
    breadcrumbs: [{ title: 'Folders', href: foldersIndex() }],
};
```

## 2. Admin list page

```tsx
// source: resources/js/pages/admin/files/index.tsx (filter + table pattern)
import { Head, router } from '@inertiajs/react';
import { type FormEvent, useEffect, useState } from 'react';
import { PageHeader } from '@/components/page-header';
import { Empty, EmptyContent, EmptyDescription, EmptyHeader, EmptyTitle } from '@/components/ui/empty';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { AdminFilesPageProps } from '@/types/admin-files';
import files from '@/routes/admin/files';

export default function FilesIndex({ rows, filters }: AdminFilesPageProps) {
    const [query, setQuery] = useState(filters.q ?? '');
    const hasFilters = Boolean(filters.q || filters.purpose || filters.scan || filters.state);

    useEffect(() => setQuery(filters.q), [filters.q]);

    function apply(event: FormEvent) {
        event.preventDefault();
        router.get(files.index().url, { q: query.trim() || undefined }, {
            preserveScroll: true,
            preserveState: true,
        });
    }

    return (
        <>
            <Head title="Files — Administration" />
            <div className="mx-auto flex w-full max-w-7xl min-w-0 flex-1 flex-col gap-5 px-5 pt-4 pb-10">
                <PageHeader title="Files" description="Retained media metadata." />
                <form onSubmit={apply} className="lp-panel flex flex-wrap gap-2 p-3">
                    <Input value={query} onChange={(e) => setQuery(e.target.value)} aria-label="Search files" />
                    <Button type="submit" variant="outline">Apply</Button>
                </form>
                <div className="lp-table-shell hidden lg:block">
                    <table data-table-surface className="w-full text-sm" aria-label="Files">
                        <thead><tr><th scope="col">Name</th><th scope="col">State</th></tr></thead>
                        <tbody>
                            {rows.data.map((row) => (
                                <tr key={row.id}>
                                    <td className="p-3 font-medium">{row.originalName}</td>
                                    <td className="p-3">{row.deletedAt ? 'Deleted' : 'Active'}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                <div className="grid gap-3 lg:hidden">
                    {rows.data.map((row) => (
                        <div key={row.id} className="lp-panel p-4">{row.originalName}</div>
                    ))}
                </div>
                {rows.data.length === 0 && (
                    <Empty>
                        <EmptyHeader>
                            <EmptyTitle>{hasFilters ? 'No files match these filters' : 'No files yet'}</EmptyTitle>
                            <EmptyDescription>
                                {hasFilters ? 'Clear filters to see retained files.' : 'Retained files will appear here.'}
                            </EmptyDescription>
                        </EmptyHeader>
                        {hasFilters && <EmptyContent>
                            <Button type="button" variant="outline" onClick={() => {
                                setQuery('');
                                router.get(files.index().url, {}, { preserveScroll: true });
                            }}>Clear filters</Button>
                        </EmptyContent>}
                    </Empty>
                )}
                {/* Admin pagination: a <nav aria-label="Files pagination"> with
                    rows.from–rows.to of rows.total plus rows.links. Do not use
                    ResourceListPagination here; use the footer pattern from
                    resources/js/pages/admin/users/index.tsx. */}
            </div>
        </>
    );
}
```

Admin lists compose their own toolbar and footer. `ResourceListPagination` is
for user lists only; guide 05 owns the rule.

## 3. Page form

```tsx
// source: resources/js/pages/settings/profile.tsx
import { Form } from '@inertiajs/react';
import { useRef } from 'react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import { Button } from '@/components/ui/button';
import { Field, FieldError, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';

const nameRef = useRef<HTMLInputElement>(null);

<Form {...ProfileController.update.form()} options={{ preserveScroll: true }} onError={() => nameRef.current?.focus()} className="space-y-6">
    {({ processing, errors }) => (
        <>
            <Field data-invalid={Boolean(errors.name)}>
                <FieldLabel htmlFor="name">Name</FieldLabel>
                <Input ref={nameRef} id="name" name="name" defaultValue={user.name} required aria-invalid={Boolean(errors.name)} aria-describedby={errors.name ? 'name-error' : undefined} />
                {errors.name && <FieldError id="name-error">{errors.name}</FieldError>}
            </Field>
            <Button disabled={processing}>
                {processing && <Spinner data-icon="inline-start" />}
                Save
            </Button>
        </>
    )}
</Form>
```

## 4. Dialog form

```tsx
// source: resources/js/components/folders/folder-form-dialog.tsx
import { useForm } from '@inertiajs/react';
import { type FormEvent, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Field, FieldError, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/folders';

const [open, setOpen] = useState(false);
const form = useForm({ name: '' });
const nameRef = useRef<HTMLInputElement>(null);

function handleOpenChange(next: boolean) {
    if (form.processing) return;
    setOpen(next);
    if (next) {
        form.clearErrors();
        form.reset();
    }
}

function submit(event: FormEvent) {
    event.preventDefault();
    form.post(store().url, {
        preserveScroll: true,
        onSuccess: () => { form.reset(); setOpen(false); },
        onError: () => nameRef.current?.focus(),
    });
}

<Dialog open={open} onOpenChange={handleOpenChange}>
    <DialogTrigger asChild><Button>Create folder</Button></DialogTrigger>
    <DialogContent>
        <DialogHeader>
            <DialogTitle id="folder-form-title" tabIndex={-1}>Create folder</DialogTitle>
            <DialogDescription>Group related links for faster management.</DialogDescription>
        </DialogHeader>
        <form onSubmit={submit}>
            <FieldGroup>
                <Field data-invalid={Boolean(form.errors.name)}>
                    <FieldLabel htmlFor="folder-name">Name</FieldLabel>
                    <Input ref={nameRef} id="folder-name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} aria-invalid={Boolean(form.errors.name)} aria-describedby={form.errors.name ? 'folder-name-error' : undefined} />
                    {form.errors.name && <FieldError id="folder-name-error">{form.errors.name}</FieldError>}
                </Field>
                <DialogFooter>
                    <DialogClose asChild><Button type="button" variant="outline" disabled={form.processing}>Cancel</Button></DialogClose>
                    <Button type="submit" disabled={form.processing}>
                        {form.processing && <Spinner data-icon="inline-start" />}
                        Create folder
                    </Button>
                </DialogFooter>
            </FieldGroup>
        </form>
    </DialogContent>
</Dialog>
```

## 5. Destructive confirm

```tsx
// source: resources/js/pages/admin/localizations/index.tsx (AlertDialog structure)
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle, AlertDialogTrigger } from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';

<AlertDialog>
    <AlertDialogTrigger asChild><Button variant="destructive">Delete</Button></AlertDialogTrigger>
    <AlertDialogContent>
        <AlertDialogHeader>
            <AlertDialogTitle>Delete this item?</AlertDialogTitle>
            <AlertDialogDescription>This cannot be undone.</AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
            <AlertDialogCancel>Cancel</AlertDialogCancel>
            <AlertDialogAction onClick={destroy}>Delete</AlertDialogAction>
        </AlertDialogFooter>
    </AlertDialogContent>
</AlertDialog>
```

If the confirmation needs a password or a form, use `Dialog` + `Form` instead.

## 6. KPI row

```tsx
// source: resources/js/pages/admin/insights/index.tsx (text-only KPI pattern)
<section aria-label="Platform totals" className="grid grid-cols-2 gap-3 lg:grid-cols-4">
    <StatCard label="Accounts" value={data.accounts.total.toLocaleString()} note={`${data.accounts.new} new in window`} />
    <StatCard label="Workspaces" value={data.workspaces.total.toLocaleString()} note={`${data.workspaces.new} new in window`} />
</section>
```

`StatCard` accepts `sparkline`; admin aggregate pages must not pass it.

## 7. Chart card

```tsx
// source: resources/js/components/admin/insights/daily-chart.tsx
import { CartesianGrid, Line, LineChart, XAxis, YAxis } from 'recharts';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ChartContainer, ChartTooltip, ChartTooltipContent } from '@/components/ui/chart';

const config = { clicks: { label: 'Clicks', color: 'var(--chart-1)' } };

<Card>
    <CardHeader><CardTitle>Daily activity</CardTitle></CardHeader>
    <CardContent>
        <ChartContainer config={config} className="h-[300px] w-full min-w-0" aria-label={`Daily clicks, ${rangeLabel}, ${total} total`}>
            <LineChart data={data} accessibilityLayer>
                <CartesianGrid vertical={false} />
                <XAxis dataKey="date" tickLine={false} axisLine={false} />
                <YAxis allowDecimals={false} tickLine={false} axisLine={false} width={36} />
                <ChartTooltip content={<ChartTooltipContent />} />
                <Line dataKey="clicks" stroke="var(--color-clicks)" strokeWidth={2} dot={false} isAnimationActive={false} />
            </LineChart>
        </ChartContainer>
    </CardContent>
</Card>
```

`ChartContainer` renders `role="img"`; pass `aria-label` on it. Empty data
renders `Empty`, not an empty axis.

## 8. Settings section

```tsx
// source: resources/js/pages/admin/settings/section.tsx
import { Button } from '@/components/ui/button';
import { FieldGroup } from '@/components/ui/field';

<div className="grid gap-4">
    <div className="grid gap-2">
        <h3 className="text-base leading-6 font-semibold">General</h3>
        <p className="text-sm text-muted-foreground">Platform name and support contact.</p>
    </div>
    <FieldGroup>{/* fields from section.groups */}</FieldGroup>
    <div className="sticky bottom-0 z-20 -mx-4 mt-1 border-t bg-background px-4 py-3 md:-mx-6 md:px-6">
        <div className="flex flex-wrap items-center gap-3">
            <Button disabled={!canSave || processing} onClick={save}>Save changes</Button>
            <p className="text-sm text-muted-foreground" aria-live="polite">
                {dirty ? 'Unsaved changes' : 'All changes saved'}
            </p>
        </div>
    </div>
</div>
```

Keep the rail/chooser from `admin/settings/layout.tsx`; do not add a page shell.

## 9. Filter toolbar

```tsx
// source: resources/js/pages/admin/content/index.tsx
const [query, setQuery] = useState(filters.q ?? '');

function apply(event: FormEvent) {
    event.preventDefault();
    router.get(path, { q: query.trim() || undefined }, {
        preserveScroll: true,
        preserveState: true,
    });
}

function reset() {
    setQuery('');
    router.get(path, {}, { preserveScroll: true });
}

<form onSubmit={apply} className="lp-panel flex flex-wrap items-center gap-2 p-3">
    <Input value={query} onChange={(e) => setQuery(e.target.value)} aria-label="Search" className="w-full sm:max-w-sm" />
    <Button type="submit">Apply</Button>
    <Button type="button" variant="ghost" onClick={reset}>Reset</Button>
</form>
```

Show active chips below the toolbar; each chip removes its own filter.

## 10. Table-to-cards block

```tsx
// source: resources/js/pages/admin/users/index.tsx
<>
<div className="lp-table-shell hidden lg:block">
    <table data-table-surface className="w-full text-sm" aria-label="Users">
        <thead><tr><th scope="col">Account</th><th scope="col" className="text-right">Links</th></tr></thead>
        <tbody>
            {rows.map((row) => (
                <tr key={row.id}>
                    <td className="p-3 font-medium">{row.name}</td>
                    <td className="p-3 text-right tabular-nums">{row.links}</td>
                </tr>
            ))}
        </tbody>
    </table>
</div>
<div className="grid gap-3 lg:hidden">
    {rows.map((row) => (
        <div key={row.id} className="lp-panel p-4">
            <p className="font-medium">{row.name}</p>
            <p className="text-sm text-muted-foreground tabular-nums">{row.links} links</p>
        </div>
    ))}
</div>
</>
```

## 11. Empty state

```tsx
// source: resources/js/pages/link-pages/index.tsx
import { Empty, EmptyContent, EmptyDescription, EmptyHeader, EmptyTitle } from '@/components/ui/empty';
import { Button } from '@/components/ui/button';

<Empty>
    <EmptyHeader>
        <EmptyTitle>No link pages yet</EmptyTitle>
        <EmptyDescription>Start with a campaign, then add its destinations.</EmptyDescription>
    </EmptyHeader>
    <EmptyContent><Button>Create page</Button></EmptyContent>
</Empty>
```

Filtered-empty copy offers “Clear filters” instead of a create action.

## 12. Status badge mapping

```tsx
// source: resources/js/pages/links/index.tsx (status mapping)
import { Badge } from '@/components/ui/badge';

function StatusBadge({ status }: { status: 'live' | 'paused' | 'archived' | 'pending' }) {
    const map = {
        live: { label: 'Live', variant: 'success' as const },
        paused: { label: 'Paused', variant: 'warning' as const },
        archived: { label: 'Archived', variant: 'secondary' as const },
        pending: { label: 'Pending review', variant: 'outline' as const },
    };
    const item = map[status];
    return <Badge variant={item.variant}>{item.label}</Badge>;
}
```

Pair a status dot with text; never communicate status by color alone.

## Checklist

- [ ] The recipe’s source file was read; imports and props match it.
- [ ] Domain types and field names replaced; no placeholder type remains.
- [ ] `PageHeader`, wrapper, and empty state follow guides 02 and 05.
- [ ] Form recipe uses the chosen API consistently; errors inline.
- [ ] Dialog footer is reachable at 390 px; focus returns to the trigger.
- [ ] Tokens and chart colors come from the theme, not literals.
- [ ] The trimmed result passes `types:check`, focused `vp check`, and the
      relevant tests.
