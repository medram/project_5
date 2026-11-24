import { Head, useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';
import { FieldError } from '@/components/ui/field';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { InvitationAcceptance } from '@/types/workspaces';

export default function AcceptWorkspaceInvitation({
    token,
    invitation,
}: InvitationAcceptance) {
    const form = useForm<{
        token?: string;
        invitation?: string;
        plan?: string;
    }>({});
    const acceptError =
        form.errors.token || form.errors.invitation || form.errors.plan;
    function accept(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post(`/invitations/${encodeURIComponent(token)}/accept`, {
            preserveScroll: true,
        });
    }

    return (
        <section className="flex min-h-svh items-center justify-center px-4 py-10">
            <Head title="Accept workspace invitation" />
            <Card className="w-full max-w-lg">
                <CardHeader>
                    <CardTitle>Join {invitation.workspace_name}</CardTitle>
                    <CardDescription>
                        You’ve been invited to join this LinkPilot workspace.
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-5">
                    <dl className="grid gap-3 rounded-md bg-muted/50 p-4 text-sm sm:grid-cols-2">
                        <div>
                            <dt className="text-muted-foreground">
                                Invited email
                            </dt>
                            <dd className="font-medium break-all">
                                {invitation.email}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-muted-foreground">
                                Workspace role
                            </dt>
                            <dd className="font-medium capitalize">
                                {invitation.role}
                            </dd>
                        </div>
                        <div className="sm:col-span-2">
                            <dt className="text-muted-foreground">
                                Invitation expires
                            </dt>
                            <dd className="font-medium">
                                {new Date(
                                    invitation.expires_at,
                                ).toLocaleString()}
                            </dd>
                        </div>
                    </dl>
                    <p className="text-sm text-muted-foreground">
                        Accept while signed in with the invited email address.
                    </p>
                    <form onSubmit={accept} className="space-y-3">
                        {acceptError && <FieldError>{acceptError}</FieldError>}
                        <Button
                            type="submit"
                            className="w-full"
                            disabled={form.processing}
                        >
                            {form.processing ? 'Joining…' : 'Accept invitation'}
                        </Button>
                    </form>
                </CardContent>
            </Card>
        </section>
    );
}
