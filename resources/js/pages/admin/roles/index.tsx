import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Check, Pencil, Plus, Save, Trash2, Users } from 'lucide-react';
import { type FormEvent, useState } from 'react';
import { AdminConfirmDialog } from '@/components/admin/admin-confirm-dialog';
import { Badge } from '@/components/ui/badge';
import { PageHeader } from '@/components/page-header';
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
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Field, FieldGroup, FieldLabel, FieldLegend, FieldSet } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import users from '@/routes/admin/users';
import type { AdminPermission, AdminRoleDefinition, AdminRolesPageProps } from '@/types/admin';

export default function AdminRolesIndex({ roles, allPermissions }: AdminRolesPageProps) {
    const { auth } = usePage().props;
    const canManage = auth.adminAbilities.includes('admin.users.manage_roles');
    const [editing, setEditing] = useState<AdminRoleDefinition | 'new' | null>(null);
    const [deleting, setDeleting] = useState<AdminRoleDefinition | null>(null);
    const [processing, setProcessing] = useState(false);
    const [deleteError, setDeleteError] = useState<string | null>(null);

    function deleteRole() {
        if (!deleting) return;
        setProcessing(true);
        router.delete(`/admin/roles/${encodeURIComponent(deleting.code)}`, {
            preserveScroll: true,
            onSuccess: () => { setDeleting(null); setDeleteError(null); },
            onError: (errors) => {
                setDeleting(null);
                setDeleteError(Object.values(errors).join(' ') || 'The role could not be deleted.');
            },
            onFinish: () => setProcessing(false),
        });
    }
    return (
        <>
            <Head title="Roles — Administration" />
            <div className="mx-auto flex w-full max-w-7xl min-w-0 flex-1 flex-col gap-5 px-5 pt-4 pb-10">
                <PageHeader
                    title="Roles and permissions"
                    description="Review permissions and members, or assign roles from an account’s detail page."
                    actions={
                        canManage && (
                            <Button
                                type="button"
                                onClick={() => setEditing('new')}
                            >
                                <Plus aria-hidden="true" />
                                Create role
                            </Button>
                        )
                    }
                />
                {deleteError && <p role="alert" className="text-sm text-destructive">{deleteError}</p>}

                <div className="grid gap-4 xl:grid-cols-2">
                    {roles.map((role) => (
                        <Card key={role.code} className="overflow-hidden">
                            <CardHeader className="gap-2">
                                <div className="flex items-start justify-between gap-3">
                                    <div>
                                        <CardTitle>{role.name}</CardTitle>
                                        <CardDescription>
                                            <code className="text-xs">
                                                {role.code}
                                            </code>
                                        </CardDescription>
                                    </div>
                                    <Badge variant="outline">{role.isBuiltIn ? 'Built-in · immutable' : 'Custom'}</Badge>
                                </div>
                                <div className="flex flex-wrap gap-2 text-sm text-muted-foreground">
                                    <span className="inline-flex items-center gap-1">
                                        <Check
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        {role.permissions.length} permissions
                                    </span>
                                    <span className="inline-flex items-center gap-1">
                                        <Users
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        {role.usersCount ?? 0}{' '}
                                        {role.usersCount === 1
                                            ? 'member'
                                            : 'members'}
                                    </span>
                                </div>
                                {canManage && (
                                    <div className="flex flex-wrap gap-2">
                                        <Button type="button" variant="outline" size="sm" disabled={role.isBuiltIn || role.isAssignedToActor} onClick={() => setEditing(role)}>
                                            <Pencil aria-hidden="true" />
                                            Edit role
                                        </Button>
                                        <Button type="button" variant="outline" size="sm" disabled={role.isBuiltIn || role.isAssignedToActor || (role.usersCount ?? 0) > 0} onClick={() => { setDeleteError(null); setDeleting(role); }}>
                                            <Trash2 aria-hidden="true" />
                                            Delete role
                                        </Button>
                                    </div>
                                )}
                                {canManage && role.isAssignedToActor && !role.isBuiltIn && <p className="text-sm text-muted-foreground">You cannot edit or delete a role assigned to you.</p>}
                                {canManage && !role.isBuiltIn && (role.usersCount ?? 0) > 0 && <p className="text-sm text-muted-foreground">Remove all members before deleting this role.</p>}
                            </CardHeader>
                            <CardContent className="grid gap-5">
                                <section>
                                    <h2 className="text-sm font-medium">
                                        Permission matrix
                                    </h2>
                                    <ul className="mt-2 grid gap-1.5 text-sm sm:grid-cols-2">
                                        {role.permissions.map((permission) => (
                                            <li
                                                key={permission.value}
                                                className="flex items-start gap-2"
                                            >
                                                <Check
                                                    className="mt-0.5 size-4 shrink-0 text-success"
                                                    aria-hidden="true"
                                                />
                                                <span className="min-w-0">
                                                    {permission.label}
                                                    <code className="block break-all text-xs text-muted-foreground">{permission.value}</code>
                                                </span>
                                            </li>
                                        ))}
                                    </ul>
                                </section>

                                <section>
                                    <div className="flex items-center justify-between gap-2">
                                        <h2 className="text-sm font-medium">
                                            Members
                                        </h2>
                                        <Link
                                            href={
                                                users.index({
                                                    query: { role: role.code },
                                                }).url
                                            }
                                            className="text-sm underline-offset-4 hover:underline"
                                        >
                                            View all
                                        </Link>
                                    </div>
                                    {(role.members ?? []).length === 0 ? (
                                        <p className="mt-2 text-sm text-muted-foreground">
                                            No accounts currently hold this
                                            role.
                                        </p>
                                    ) : (
                                        <ul className="mt-2 divide-y rounded-md border">
                                            {(role.members ?? []).map(
                                                (member) => (
                                                    <li
                                                        key={member.id}
                                                        className="flex min-w-0 flex-col gap-0.5 px-3 py-2 sm:flex-row sm:items-center sm:justify-between"
                                                    >
                                                        <Link
                                                            href={
                                                                users.show(
                                                                    member.id,
                                                                ).url
                                                            }
                                                            className="truncate font-medium hover:underline"
                                                        >
                                                            {member.name}
                                                        </Link>
                                                        <span className="truncate text-xs text-muted-foreground">
                                                            {member.email}
                                                        </span>
                                                    </li>
                                                ),
                                            )}
                                        </ul>
                                    )}
                                </section>
                            </CardContent>
                        </Card>
                    ))}
                </div>
            {editing !== null && (
                <RoleEditor
                    role={editing === 'new' ? null : editing}
                    permissions={allPermissions}
                    abilities={auth.adminAbilities}
                    onClose={() => setEditing(null)}
                />
            )}
            <AdminConfirmDialog
                confirmation={deleting ? {
                    title: 'Delete role?',
                    description: `Delete ${deleting.name}? This cannot be undone.`,
                    confirmLabel: 'Delete role',
                    destructive: true,
                } : null}
                processing={processing}
                onCancel={() => setDeleting(null)}
                onConfirm={deleteRole}
            />
            </div>
        </>
    );
}

function RoleEditor({
    role,
    permissions,
    abilities,
    onClose,
}: {
    role: AdminRoleDefinition | null;
    permissions: AdminPermission[];
    abilities: string[];
    onClose: () => void;
}) {
    const form = useForm({
        name: role?.name ?? '',
        code: role?.code ?? '',
        permissions: role?.permissions.map((permission) => permission.value) ?? [],
    });
    const [confirming, setConfirming] = useState(false);
    const original = role?.permissions.map((permission) => permission.value) ?? [];
    const permissionsChanged = original.length !== form.data.permissions.length ||
        original.some((value) => !form.data.permissions.includes(value));

    function save() {
        setConfirming(false);
        if (role) {
            form.transform((data) => ({ name: data.name, permissions: data.permissions }));
            form.patch(`/admin/roles/${encodeURIComponent(role.code)}`, {
                preserveScroll: true,
                onSuccess: onClose,
            });
        } else {
            form.post('/admin/roles', { preserveScroll: true, onSuccess: onClose });
        }
    }

    function submit(event: FormEvent) {
        event.preventDefault();
        if (role && (role.usersCount ?? 0) > 0 && permissionsChanged) {
            setConfirming(true);
        } else {
            save();
        }
    }

    return (
        <>
            <Dialog open onOpenChange={(open) => { if (!open && !form.processing) onClose(); }}>
                <DialogContent className="flex max-h-[calc(100dvh-2rem)] flex-col gap-0 overflow-hidden sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>{role ? 'Edit role' : 'Create role'}</DialogTitle>
                        <DialogDescription>
                            Choose permissions explicitly. Navigation requires admin.access and the relevant resource view permission.
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={submit} className="flex min-h-0 flex-1 flex-col">
                        <div className="min-h-0 overflow-y-auto py-4">
                            <FieldGroup>
                                <Field>
                                    <FieldLabel htmlFor="role-name">Name</FieldLabel>
                                    <Input id="role-name" value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} aria-invalid={!!form.errors.name} aria-describedby={form.errors.name ? 'role-name-error' : undefined} />
                                    {form.errors.name && <p id="role-name-error" className="text-sm text-destructive">{form.errors.name}</p>}
                                </Field>
                                <Field>
                                    <FieldLabel htmlFor="role-code">Code</FieldLabel>
                                    <Input id="role-code" value={form.data.code} onChange={(event) => form.setData('code', event.target.value)} disabled={!!role} aria-invalid={!!form.errors.code} aria-describedby={form.errors.code ? 'role-code-error' : 'role-code-help'} />
                                    <p id="role-code-help" className="text-sm text-muted-foreground">Lowercase letters, numbers and underscores. The code cannot be changed later.</p>
                                    {form.errors.code && <p id="role-code-error" className="text-sm text-destructive">{form.errors.code}</p>}
                                </Field>
                                <FieldSet>
                                    <FieldLegend>Permissions</FieldLegend>
                                    <p className="text-sm text-muted-foreground">You can only select permissions you hold. No permissions are added automatically.</p>
                                    <div className="grid gap-1 sm:grid-cols-2">
                                        {permissions.map((permission, index) => {
                                            const id = `role-permission-${index}`;
                                            return (
                                                <div key={permission.value} className="flex min-w-0 items-start gap-2 rounded-md p-2">
                                                    <Checkbox
                                                        id={id}
                                                        aria-label={permission.label}
                                                        checked={form.data.permissions.includes(permission.value)}
                                                        disabled={!abilities.includes(permission.value)}
                                                        onCheckedChange={(checked) => form.setData('permissions', checked === true
                                                            ? [...form.data.permissions, permission.value]
                                                            : form.data.permissions.filter((value) => value !== permission.value))}
                                                    />
                                                    <Label htmlFor={id} className="flex min-w-0 flex-1 flex-col items-start gap-1 leading-normal">
                                                        <span>{permission.label}</span>
                                                        <code className="break-all text-xs text-muted-foreground">{permission.value}</code>
                                                    </Label>
                                                </div>
                                            );
                                        })}
                                    </div>
                                </FieldSet>
                                {Object.keys(form.errors).length > 0 && (
                                    <div role="alert" className="text-sm text-destructive">
                                        {Object.entries(form.errors).map(([key, error]) => <p key={key}>{error}</p>)}
                                    </div>
                                )}
                            </FieldGroup>
                        </div>
                        <DialogFooter className="shrink-0 gap-2 border-t pt-4">
                            <Button type="button" variant="outline" disabled={form.processing} onClick={onClose}>Cancel</Button>
                            <Button type="submit" disabled={form.processing}><Save aria-hidden="true" />{form.processing ? 'Saving…' : role ? 'Save changes' : 'Create role'}</Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
            <AdminConfirmDialog
                confirmation={confirming && role ? {
                    title: 'Change permissions?',
                    description: `This changes permissions for ${role.usersCount} assigned ${role.usersCount === 1 ? 'member' : 'members'}.`,
                    confirmLabel: 'Save changes',
                } : null}
                processing={form.processing}
                onCancel={() => setConfirming(false)}
                onConfirm={save}
            />
        </>
    );
}
