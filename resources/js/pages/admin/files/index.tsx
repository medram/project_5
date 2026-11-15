import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';
import { Trash2 } from 'lucide-react';
import { formatAdminDate, humanizeAdminValue } from '@/components/admin/format';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
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
import files from '@/routes/admin/files';
import type { AdminFileRow, AdminFilesPageProps } from '@/types/admin-files';

function bytes(value: number): string {
    if (value >= 1024 * 1024) {
        return `${(value / 1024 / 1024).toFixed(1)} MB`;
    }

    return `${(value / 1024).toFixed(1)} KB`;
}

export default function AdminFilesIndex({
    rows,
    filters,
    purposes,
}: AdminFilesPageProps) {
    const { auth } = usePage().props;
    const canManage = auth.adminAbilities.includes('admin.files.manage');
    const [query, setQuery] = useState(filters.q);
    const [purpose, setPurpose] = useState(filters.purpose ?? 'any');
    const [scan, setScan] = useState(filters.scan ?? 'any');
    const [state, setState] = useState(filters.state ?? 'any');
    const [sort, setSort] = useState(filters.sort);
    const direction = filters.direction;
    const [target, setTarget] = useState<{
        mode: 'delete' | 'restore';
        row: AdminFileRow;
    } | null>(null);

    function apply(event: FormEvent) {
        event.preventDefault();
        router.get(
            files.index().url,
            {
                q: query.trim() || undefined,
                purpose: purpose === 'any' ? undefined : purpose,
                scan: scan === 'any' ? undefined : scan,
                state: state === 'any' ? undefined : state,
                sort,
                direction,
            },
            { preserveScroll: true },
        );
    }

    return (
        <>
            <Head title="Files — Administration" />
            <div className="mx-auto flex w-full max-w-7xl min-w-0 flex-1 flex-col gap-5 px-5 pt-4 pb-10">
                <header className="space-y-1">
                    <h1 className="text-2xl leading-8 font-semibold tracking-[-0.02em]">
                        Files
                    </h1>
                    <p className="max-w-3xl text-sm text-muted-foreground">
                        Retained media metadata across workspaces. Disk paths,
                        previews, downloads, and tenant file contents are never
                        exposed. Soft deletion is reversible; physical purge
                        remains retention-driven.
                    </p>
                </header>

                <Card>
                    <CardHeader className="gap-1">
                        <CardTitle>Filters</CardTitle>
                        <CardDescription>
                            Search by name, SHA-256, workspace, or uploader.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form
                            onSubmit={apply}
                            className="grid gap-3 sm:grid-cols-2 xl:grid-cols-[minmax(0,2fr)_repeat(4,minmax(0,1fr))_auto]"
                        >
                            <div className="grid gap-2">
                                <Label htmlFor="file-search">Search</Label>
                                <Input
                                    id="file-search"
                                    value={query}
                                    maxLength={100}
                                    onChange={(event) =>
                                        setQuery(event.target.value)
                                    }
                                />
                            </div>
                            <Filter
                                id="file-purpose"
                                label="Purpose"
                                value={purpose}
                                onChange={setPurpose}
                                options={[
                                    ['any', 'Any purpose'],
                                    ...purposes.map(
                                        (item) =>
                                            [item.value, item.label] as [
                                                string,
                                                string,
                                            ],
                                    ),
                                ]}
                            />
                            <Filter
                                id="file-scan"
                                label="Scan"
                                value={scan}
                                onChange={setScan}
                                options={[
                                    ['any', 'Any scan'],
                                    ['not_required', 'Not required'],
                                    ['clean', 'Clean'],
                                    ['infected', 'Infected'],
                                    ['failed', 'Failed'],
                                ]}
                            />
                            <Filter
                                id="file-state"
                                label="State"
                                value={state}
                                onChange={setState}
                                options={[
                                    ['any', 'Any state'],
                                    ['active', 'Active'],
                                    ['trashed', 'Trashed'],
                                ]}
                            />
                            <Filter
                                id="file-sort"
                                label="Sort"
                                value={sort}
                                onChange={setSort}
                                options={[
                                    ['created_at', 'Created'],
                                    ['updated_at', 'Updated'],
                                    ['byte_size', 'Size'],
                                ]}
                            />
                            <div className="flex items-end gap-2">
                                <Button
                                    type="submit"
                                    className="min-h-9 flex-1"
                                >
                                    Apply
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    className="min-h-9"
                                    onClick={() =>
                                        router.get(files.index().url)
                                    }
                                >
                                    Reset
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <div className="lp-table-shell hidden lg:block">
                    <table
                        data-table-surface={
                            rows.data.length > 0 ? 'populated' : undefined
                        }
                        className="w-full border-collapse text-sm"
                    >
                        <thead className="bg-muted/40 text-left text-xs text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3 font-medium">File</th>
                                <th className="px-4 py-3 font-medium">
                                    Workspace / uploader
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Purpose / scan
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Size / hash
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    States
                                </th>
                                <th className="px-4 py-3 text-right font-medium">
                                    Manage
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.data.map((row) => (
                                <tr key={row.id} className="border-t align-top">
                                    <td className="max-w-72 px-4 py-3">
                                        <p className="truncate font-medium">
                                            {row.originalName}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {row.mimeType} · {row.visibility}
                                        </p>
                                    </td>
                                    <td className="max-w-64 px-4 py-3">
                                        <p className="truncate">
                                            {row.workspace ?? '—'}
                                        </p>
                                        <p className="truncate text-xs text-muted-foreground">
                                            {row.uploader ?? '—'}
                                            {row.uploaderEmail
                                                ? ` · ${row.uploaderEmail}`
                                                : ''}
                                        </p>
                                    </td>
                                    <td className="px-4 py-3">
                                        <p>{row.purpose}</p>
                                        <Badge variant="outline">
                                            {humanizeAdminValue(row.scanState)}
                                        </Badge>
                                    </td>
                                    <td className="px-4 py-3">
                                        <p>{bytes(row.byteSize)}</p>
                                        <p
                                            className="max-w-32 truncate font-mono text-xs text-muted-foreground"
                                            title={row.sha256}
                                        >
                                            {row.sha256}
                                        </p>
                                    </td>
                                    <td className="px-4 py-3">
                                        {row.deletedAt ? (
                                            <Badge variant="destructive">
                                                Deleted
                                            </Badge>
                                        ) : (
                                            <Badge variant="secondary">
                                                Active
                                            </Badge>
                                        )}
                                        <p className="text-xs text-muted-foreground">
                                            Expires{' '}
                                            {formatAdminDate(row.expiresAt)}
                                        </p>
                                        {row.deletionReason && (
                                            <p className="max-w-48 text-xs text-muted-foreground">
                                                {row.deletionReason}
                                            </p>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        {canManage &&
                                            (row.deletedAt ? (
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant="outline"
                                                    className="min-h-9"
                                                    onClick={() =>
                                                        setTarget({
                                                            mode: 'restore',
                                                            row,
                                                        })
                                                    }
                                                >
                                                    Restore
                                                </Button>
                                            ) : (
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant="outline"
                                                    className="min-h-9"
                                                    onClick={() =>
                                                        setTarget({
                                                            mode: 'delete',
                                                            row,
                                                        })
                                                    }
                                                >
                                                    <Trash2 aria-hidden="true" />
                                                    Soft delete
                                                </Button>
                                            ))}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    {rows.data.length === 0 && (
                        <p className="p-8 text-center text-sm text-muted-foreground">
                            No retained media match these filters.
                        </p>
                    )}
                </div>

                <div className="grid gap-3 lg:hidden">
                    {rows.data.map((row) => (
                        <Card key={row.id}>
                            <CardHeader className="gap-1">
                                <div className="flex items-start justify-between gap-2">
                                    <CardTitle className="truncate text-base">
                                        {row.originalName}
                                    </CardTitle>
                                    <Badge
                                        variant={
                                            row.deletedAt
                                                ? 'destructive'
                                                : 'secondary'
                                        }
                                    >
                                        {row.deletedAt ? 'Deleted' : 'Active'}
                                    </Badge>
                                </div>
                                <CardDescription className="truncate">
                                    {row.workspace ?? '—'} ·{' '}
                                    {row.uploader ?? '—'}
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-2 text-sm">
                                <p>
                                    {row.purpose} · {row.mimeType} ·{' '}
                                    {bytes(row.byteSize)}
                                </p>
                                <p className="truncate font-mono text-xs text-muted-foreground">
                                    {row.sha256}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    Scan {humanizeAdminValue(row.scanState)} ·
                                    Expires {formatAdminDate(row.expiresAt)}
                                </p>
                                {row.deletionReason && (
                                    <p className="text-xs text-muted-foreground">
                                        {row.deletionReason}
                                    </p>
                                )}
                                {canManage && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        className="min-h-11"
                                        onClick={() =>
                                            setTarget({
                                                mode: row.deletedAt
                                                    ? 'restore'
                                                    : 'delete',
                                                row,
                                            })
                                        }
                                    >
                                        {row.deletedAt
                                            ? 'Restore metadata'
                                            : 'Soft delete'}
                                    </Button>
                                )}
                            </CardContent>
                        </Card>
                    ))}
                    {rows.data.length === 0 && (
                        <Card variant="transparent">
                            <CardContent className="p-8 text-center text-sm text-muted-foreground">
                                No retained media match these filters.
                            </CardContent>
                        </Card>
                    )}
                </div>

                <footer className="flex flex-col gap-3 text-sm text-muted-foreground sm:flex-row sm:items-center sm:justify-between">
                    <p>
                        Showing {rows.from ?? 0}–{rows.to ?? 0} of{' '}
                        {rows.total.toLocaleString()} files
                    </p>
                    {rows.last_page > 1 && (
                        <nav className="flex flex-wrap gap-1">
                            {rows.links.map((link) =>
                                link.url === null ? (
                                    <span
                                        key={link.label}
                                        className="rounded-md border px-3 py-2 opacity-50"
                                    >
                                        {link.label
                                            .replace('&laquo;', '‹')
                                            .replace('&raquo;', '›')}
                                    </span>
                                ) : (
                                    <Link
                                        key={link.label}
                                        href={link.url}
                                        preserveScroll
                                        className={`min-h-9 rounded-md border px-3 py-2 ${
                                            link.active
                                                ? 'bg-primary text-primary-foreground'
                                                : 'hover:bg-muted'
                                        }`}
                                    >
                                        {link.label
                                            .replace('&laquo;', '‹')
                                            .replace('&raquo;', '›')}
                                    </Link>
                                ),
                            )}
                        </nav>
                    )}
                </footer>
            </div>

            <FileReasonDialog target={target} onClose={() => setTarget(null)} />
        </>
    );
}

function Filter({
    id,
    label,
    value,
    options,
    onChange,
}: {
    id: string;
    label: string;
    value: string;
    options: Array<[string, string]>;
    onChange: (value: string) => void;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            <Select value={value} onValueChange={onChange}>
                <SelectTrigger id={id} className="min-h-9 w-full">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    {options.map(([option, optionLabel]) => (
                        <SelectItem key={option} value={option}>
                            {optionLabel}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </div>
    );
}

function FileReasonDialog({
    target,
    onClose,
}: {
    target: { mode: 'delete' | 'restore'; row: AdminFileRow } | null;
    onClose: () => void;
}) {
    const form = useForm({ reason: '' });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        if (!target) {
            return;
        }

        const options = {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                onClose();
            },
        };

        if (target.mode === 'delete') {
            form.delete(files.destroy(target.row.id).url, options);
        } else {
            form.patch(files.restore(target.row.id).url, options);
        }
    }

    return (
        <Dialog
            open={target !== null}
            onOpenChange={(open) => {
                if (!open) {
                    onClose();
                }
            }}
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {target?.mode === 'delete'
                            ? 'Soft-delete file metadata?'
                            : 'Restore file metadata?'}
                    </DialogTitle>
                    <DialogDescription>
                        {target?.row.originalName} · Physical purge remains
                        retention-driven and is never triggered here.
                    </DialogDescription>
                </DialogHeader>
                <form className="grid gap-3" onSubmit={submit}>
                    <div className="grid gap-2">
                        <Label htmlFor="file-reason">Reason</Label>
                        <Textarea
                            id="file-reason"
                            value={form.data.reason}
                            maxLength={500}
                            required
                            rows={3}
                            onChange={(event) =>
                                form.setData('reason', event.target.value)
                            }
                        />
                        {form.errors.reason && (
                            <p className="text-sm text-destructive">
                                {form.errors.reason}
                            </p>
                        )}
                    </div>
                    <DialogFooter className="gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            disabled={form.processing}
                            onClick={onClose}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            variant={
                                target?.mode === 'delete'
                                    ? 'destructive'
                                    : 'default'
                            }
                            disabled={form.processing}
                        >
                            {form.processing
                                ? 'Working…'
                                : target?.mode === 'delete'
                                  ? 'Soft delete'
                                  : 'Restore'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
