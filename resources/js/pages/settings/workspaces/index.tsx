import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Copy, RefreshCw, UserPlus, Users } from 'lucide-react';
import { type FormEvent, useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type {
    WorkspaceContext,
    WorkspaceInvitation,
    WorkspaceMember,
    WorkspaceSettingsProps,
} from '@/types/workspaces';

function dateLabel(value: string | null) {
    if (!value) return 'Not sent';
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? value : date.toLocaleDateString();
}

export default function WorkspaceSettings({
    workspaces,
    members,
    invitations = [],
    canManageMembers,
    canTransferOwnership,
    memberCapacity,
    flash,
}: WorkspaceSettingsProps) {
    const { workspace: shared } = usePage<{ workspace?: WorkspaceContext }>()
        .props;
    const createWorkspace = useForm({ name: '' });
    const invite = useForm({ email: '', role: 'member' as 'admin' | 'member' });
    const planError = (invite.errors as Record<string, string | undefined>)
        .plan;
    const transfer = useForm({ password: '', user_id: '' });
    const [transferOpen, setTransferOpen] = useState(false);

    const [copiedUrl, setCopiedUrl] = useState<string | null>(null);
    const [copyError, setCopyError] = useState<string | null>(null);
    const active = shared?.current;
    const isOwner = active?.role === 'owner';

    function sendInvitation(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (!active) return;
        invite.transform((data) => ({
            ...data,
            role: isOwner ? data.role : 'member',
        }));
        invite.post(`/workspaces/${active.id}/invitations`, {
            preserveScroll: true,
            onSuccess: () => invite.reset('email'),
        });
    }

    function submitWorkspace(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        createWorkspace.post('/workspaces', {
            preserveScroll: true,
            onSuccess: () => createWorkspace.reset(),
        });
    }

    function updateMember(member: WorkspaceMember, role: string) {
        if (!active || role === member.role) return;
        router.patch(
            `/workspaces/${active.id}/members/${member.id}`,
            { role },
            { preserveScroll: true },
        );
    }

    function removeMember(member: WorkspaceMember) {
        if (
            !active ||
            !window.confirm(
                `Remove ${member.name || member.email} from this workspace?`,
            )
        )
            return;
        router.delete(`/workspaces/${active.id}/members/${member.id}`, {
            preserveScroll: true,
        });
    }

    function invitationAction(
        invitation: WorkspaceInvitation,
        action: 'resend' | 'delete',
    ) {
        if (!active) return;
        if (action === 'resend')
            router.post(
                `/workspaces/${active.id}/invitations/${invitation.id}/resend`,
                {},
                { preserveScroll: true },
            );
        else if (
            window.confirm(`Revoke the invitation for ${invitation.email}?`)
        )
            router.delete(
                `/workspaces/${active.id}/invitations/${invitation.id}`,
                { preserveScroll: true },
            );
    }

    function submitTransfer(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (
            !active ||
            !window.confirm(
                'Transfer workspace ownership? You will lose owner access.',
            )
        )
            return;
        transfer.post(`/workspaces/${active.id}/transfer`, {
            preserveScroll: true,
            onSuccess: () => {
                transfer.reset();
                setTransferOpen(false);
            },
        });
    }

    async function copyInvitationUrl() {
        if (!flash?.invitationUrl) return;
        setCopyError(null);
        if (!navigator.clipboard) {
            setCopyError(
                'Clipboard access is unavailable. Select the link and copy it.',
            );
            return;
        }
        try {
            await navigator.clipboard.writeText(flash.invitationUrl);
            setCopiedUrl(flash.invitationUrl);
        } catch {
            setCopyError(
                'Could not copy the link. Select it and copy it manually.',
            );
        }
    }

    return (
        <>
            <Head title="Workspaces" />
            <div className="space-y-6">
                <Heading
                    title="Workspaces"
                    description="Choose where your team manages links and shared resources."
                />
                {active && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Current workspace
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="flex flex-wrap items-center justify-between gap-4">
                            <div className="min-w-0">
                                <p className="text-lg font-semibold break-words">
                                    {active.name}
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    {active.slug} · Your role: {active.role}
                                </p>
                            </div>
                            <Badge variant="secondary">{active.role}</Badge>
                        </CardContent>
                    </Card>
                )}
                {flash?.success && !flash.invitationUrl && (
                    <p
                        className="rounded-md border border-primary/30 bg-muted/50 p-4 text-sm"
                        role="status"
                    >
                        {flash.success}
                    </p>
                )}
                {flash?.invitationUrl && (
                    <div
                        className="rounded-md border border-primary/30 bg-muted/50 p-4"
                        role="status"
                    >
                        <p className="font-medium">Invitation sent</p>
                        <p className="mt-1 text-sm break-all text-muted-foreground">
                            Share this invitation link with the recipient. It is
                            shown once.
                        </p>
                        <div className="mt-3 flex flex-wrap gap-2">
                            <Input
                                aria-label="Invitation link"
                                readOnly
                                value={flash.invitationUrl}
                                className="min-h-11 min-w-0 flex-1"
                            />
                            <Button
                                className="min-h-11"
                                type="button"
                                variant="outline"
                                onClick={copyInvitationUrl}
                            >
                                <Copy className="size-4" />
                                {copiedUrl === flash.invitationUrl
                                    ? 'Copied'
                                    : 'Copy link'}
                            </Button>
                        </div>
                        {copyError && (
                            <p
                                className="mt-2 text-sm text-destructive"
                                role="alert"
                            >
                                {copyError}
                            </p>
                        )}
                    </div>
                )}
                <Card>
                    <CardHeader>
                        <CardTitle>Create a workspace</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form
                            onSubmit={submitWorkspace}
                            className="grid gap-4 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end"
                        >
                            <div className="space-y-2">
                                <Label htmlFor="workspace-name">
                                    Workspace name
                                </Label>
                                <Input
                                    id="workspace-name"
                                    required
                                    maxLength={120}
                                    value={createWorkspace.data.name}
                                    onChange={(event) =>
                                        createWorkspace.setData(
                                            'name',
                                            event.target.value,
                                        )
                                    }
                                    aria-invalid={!!createWorkspace.errors.name}
                                />
                                <InputError
                                    message={createWorkspace.errors.name}
                                />
                            </div>
                            <Button
                                className="min-h-11"
                                type="submit"
                                disabled={createWorkspace.processing}
                            >
                                {createWorkspace.processing
                                    ? 'Creating…'
                                    : 'Create workspace'}
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <section
                    className="space-y-3"
                    aria-labelledby="your-workspaces-heading"
                >
                    <h2
                        id="your-workspaces-heading"
                        className="text-lg font-semibold"
                    >
                        Your workspaces
                    </h2>
                    <div className="divide-y rounded-lg border">
                        {workspaces.map((workspace) => (
                            <div
                                key={workspace.id}
                                className="flex flex-wrap items-center justify-between gap-3 p-4"
                            >
                                <div className="min-w-0">
                                    <p className="font-medium break-words">
                                        {workspace.name}
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        {workspace.slug}
                                    </p>
                                </div>
                                <div className="flex items-center gap-3">
                                    <Badge variant="outline">
                                        {workspace.role}
                                    </Badge>
                                    {active?.id !== workspace.id && (
                                        <Button
                                            className="min-h-11"
                                            type="button"
                                            variant="outline"
                                            onClick={() =>
                                                router.post(
                                                    `/workspaces/${workspace.id}/switch`,
                                                    {},
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            Switch to workspace
                                        </Button>
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>
                </section>
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <Users className="size-4" />
                            Member capacity
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p className="font-medium">
                            {memberCapacity.members} members
                            {memberCapacity.limit === null
                                ? ' · Unlimited plan capacity'
                                : ` of ${memberCapacity.limit}`}
                        </p>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {memberCapacity.pending} pending invitations ·{' '}
                            {memberCapacity.available === null
                                ? 'No member limit'
                                : `${memberCapacity.available} available`}{' '}
                            · {memberCapacity.plan} plan
                        </p>
                    </CardContent>
                </Card>
                {canManageMembers && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <UserPlus className="size-4" />
                                Invite a teammate
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <form
                                onSubmit={sendInvitation}
                                className="grid gap-4 sm:grid-cols-[minmax(0,1fr)_9rem_auto] sm:items-end"
                            >
                                <div className="space-y-2">
                                    <Label htmlFor="invite-email">
                                        Email address
                                    </Label>
                                    <Input
                                        id="invite-email"
                                        type="email"
                                        autoComplete="email"
                                        required
                                        value={invite.data.email}
                                        onChange={(e) =>
                                            invite.setData(
                                                'email',
                                                e.target.value,
                                            )
                                        }
                                        aria-invalid={!!invite.errors.email}
                                    />
                                    <InputError message={invite.errors.email} />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="invite-role">Role</Label>
                                    <select
                                        id="invite-role"
                                        value={invite.data.role}
                                        onChange={(e) =>
                                            invite.setData(
                                                'role',
                                                e.target.value as
                                                    | 'admin'
                                                    | 'member',
                                            )
                                        }
                                        aria-invalid={!!invite.errors.role}
                                        className="min-h-11 w-full rounded-md border bg-background px-3 text-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                    >
                                        <option value="member">Member</option>
                                        {isOwner && (
                                            <option value="admin">Admin</option>
                                        )}
                                    </select>
                                </div>
                                <Button
                                    className="min-h-11"
                                    type="submit"
                                    disabled={
                                        invite.processing ||
                                        memberCapacity.available === 0
                                    }
                                >
                                    {invite.processing
                                        ? 'Sending…'
                                        : 'Send invitation'}
                                </Button>
                                <p
                                    className="text-sm text-destructive sm:col-span-3"
                                    role="alert"
                                >
                                    {planError ||
                                        invite.errors.role ||
                                        invite.errors.email ||
                                        (memberCapacity.available === 0
                                            ? 'Member capacity is full. Change your plan or remove a member before inviting.'
                                            : '')}
                                </p>
                            </form>
                        </CardContent>
                    </Card>
                )}
                <section
                    className="space-y-3"
                    aria-labelledby="members-heading"
                >
                    <h2 id="members-heading" className="text-lg font-semibold">
                        Members{' '}
                        <span className="text-sm font-normal text-muted-foreground">
                            ({members.length})
                        </span>
                    </h2>
                    {members.length ? (
                        <div className="divide-y rounded-lg border">
                            {members.map((member) => {
                                const canEditMember =
                                    canManageMembers &&
                                    member.role !== 'owner' &&
                                    (isOwner || member.role === 'member');
                                return (
                                    <div
                                        key={member.id}
                                        className="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between"
                                    >
                                        <div className="min-w-0">
                                            <p className="font-medium break-words">
                                                {member.name || member.email}
                                            </p>
                                            <p className="text-sm break-all text-muted-foreground">
                                                {member.email} · Joined{' '}
                                                {dateLabel(member.joined_at)}
                                            </p>
                                        </div>
                                        <div className="flex flex-wrap items-center gap-2">
                                            {canEditMember ? (
                                                <>
                                                    {isOwner ? (
                                                        <>
                                                            <Label
                                                                htmlFor={`member-role-${member.id}`}
                                                                className="sr-only"
                                                            >
                                                                Role for{' '}
                                                                {member.email}
                                                            </Label>
                                                            <select
                                                                id={`member-role-${member.id}`}
                                                                value={
                                                                    member.role
                                                                }
                                                                onChange={(e) =>
                                                                    updateMember(
                                                                        member,
                                                                        e.target
                                                                            .value,
                                                                    )
                                                                }
                                                                className="min-h-11 rounded-md border bg-background px-3 text-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                                            >
                                                                <option value="member">
                                                                    Member
                                                                </option>
                                                                <option value="admin">
                                                                    Admin
                                                                </option>
                                                            </select>
                                                        </>
                                                    ) : (
                                                        <Badge variant="outline">
                                                            Member
                                                        </Badge>
                                                    )}
                                                    <Button
                                                        className="min-h-11"
                                                        type="button"
                                                        variant="outline"
                                                        onClick={() =>
                                                            removeMember(member)
                                                        }
                                                    >
                                                        Remove
                                                    </Button>
                                                </>
                                            ) : (
                                                <Badge variant="outline">
                                                    {member.role}
                                                </Badge>
                                            )}
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    ) : (
                        <p className="rounded-lg border border-dashed p-6 text-sm text-muted-foreground">
                            No workspace members are available.
                        </p>
                    )}
                </section>
                {canManageMembers && (
                    <section
                        className="space-y-3"
                        aria-labelledby="invitations-heading"
                    >
                        <h2
                            id="invitations-heading"
                            className="text-lg font-semibold"
                        >
                            Invitations
                        </h2>
                        {invitations.length ? (
                            <div className="divide-y rounded-lg border">
                                {invitations.map((invitation) => (
                                    <div
                                        key={invitation.id}
                                        className="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between"
                                    >
                                        <div className="min-w-0">
                                            <p className="font-medium break-all">
                                                {invitation.email}
                                            </p>
                                            <p className="text-sm text-muted-foreground">
                                                {invitation.role} · Sent{' '}
                                                {dateLabel(invitation.sent_at)}{' '}
                                                · Expires{' '}
                                                {dateLabel(
                                                    invitation.expires_at,
                                                )}
                                            </p>
                                        </div>
                                        <div className="flex flex-wrap items-center gap-2">
                                            <Badge
                                                variant={
                                                    invitation.status ===
                                                    'pending'
                                                        ? 'secondary'
                                                        : 'outline'
                                                }
                                            >
                                                {invitation.status}
                                            </Badge>
                                            {canManageMembers &&
                                                (isOwner ||
                                                    invitation.role ===
                                                        'member') &&
                                                invitation.status !==
                                                    'revoked' && (
                                                    <>
                                                        {(invitation.status ===
                                                            'pending' ||
                                                            invitation.status ===
                                                                'expired') && (
                                                            <Button
                                                                className="min-h-11"
                                                                type="button"
                                                                variant="outline"
                                                                onClick={() =>
                                                                    invitationAction(
                                                                        invitation,
                                                                        'resend',
                                                                    )
                                                                }
                                                            >
                                                                <RefreshCw className="size-4" />
                                                                Resend
                                                            </Button>
                                                        )}
                                                        {invitation.status ===
                                                            'pending' && (
                                                            <Button
                                                                className="min-h-11"
                                                                type="button"
                                                                variant="outline"
                                                                onClick={() =>
                                                                    invitationAction(
                                                                        invitation,
                                                                        'delete',
                                                                    )
                                                                }
                                                            >
                                                                Revoke
                                                            </Button>
                                                        )}
                                                    </>
                                                )}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <p className="rounded-lg border border-dashed p-6 text-sm text-muted-foreground">
                                No invitations have been sent.
                            </p>
                        )}
                    </section>
                )}
                {canTransferOwnership && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Transfer ownership
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <p className="text-sm text-muted-foreground">
                                Ownership changes workspace control to another
                                current member.
                            </p>
                            {!transferOpen ? (
                                <Button
                                    variant="outline"
                                    onClick={() => setTransferOpen(true)}
                                >
                                    Transfer ownership
                                </Button>
                            ) : (
                                <form
                                    onSubmit={submitTransfer}
                                    className="grid gap-4 sm:grid-cols-2"
                                >
                                    <div className="space-y-2">
                                        <Label htmlFor="transfer-member">
                                            New owner
                                        </Label>
                                        <select
                                            id="transfer-member"
                                            required
                                            value={transfer.data.user_id}
                                            onChange={(e) =>
                                                transfer.setData(
                                                    'user_id',
                                                    e.target.value,
                                                )
                                            }
                                            className="min-h-11 w-full rounded-md border bg-background px-3 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                        >
                                            <option value="">
                                                Choose a member
                                            </option>
                                            {members
                                                .filter(
                                                    (m) => m.role !== 'owner',
                                                )
                                                .map((m) => (
                                                    <option
                                                        key={m.id}
                                                        value={m.id}
                                                    >
                                                        {m.name || m.email} (
                                                        {m.email})
                                                    </option>
                                                ))}
                                        </select>
                                        <InputError
                                            message={transfer.errors.user_id}
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="transfer-password">
                                            Confirm your password
                                        </Label>
                                        <Input
                                            className="min-h-11"
                                            id="transfer-password"
                                            type="password"
                                            autoComplete="current-password"
                                            required
                                            value={transfer.data.password}
                                            onChange={(e) =>
                                                transfer.setData(
                                                    'password',
                                                    e.target.value,
                                                )
                                            }
                                            aria-invalid={
                                                !!transfer.errors.password
                                            }
                                        />
                                        <InputError
                                            message={transfer.errors.password}
                                        />
                                    </div>
                                    <div className="flex flex-wrap gap-2 sm:col-span-2">
                                        <Button
                                            className="min-h-11"
                                            type="submit"
                                            disabled={
                                                transfer.processing ||
                                                !transfer.data.user_id
                                            }
                                        >
                                            {transfer.processing
                                                ? 'Transferring…'
                                                : 'Confirm transfer'}
                                        </Button>
                                        <Button
                                            className="min-h-11"
                                            type="button"
                                            variant="ghost"
                                            onClick={() => {
                                                setTransferOpen(false);
                                                transfer.reset();
                                                transfer.clearErrors();
                                            }}
                                        >
                                            Cancel
                                        </Button>
                                    </div>
                                </form>
                            )}
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}
