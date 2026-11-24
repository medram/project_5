import { Form, Head, router } from '@inertiajs/react';
import { useRef } from 'react';
import SecurityController from '@/actions/App/Http/Controllers/Settings/SecurityController';
import Heading from '@/components/heading';
import { PageHeader } from '@/components/page-header';
import { FieldError } from '@/components/ui/field';
import PasswordInput from '@/components/password-input';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/security';
import type { Props as ManagePasskeysProps } from '@/components/manage-passkeys';
import ManagePasskeys from '@/components/manage-passkeys';
import type { Props as ManageTwoFactorProps } from '@/components/manage-two-factor';
import ManageTwoFactor from '@/components/manage-two-factor';

// oxfmt-ignore
type Session = {
    id: string;
    device: string;
    lastActivity: string;
    isCurrent: boolean;
};

type Props = {
    passwordRules: string;
    sessions: Session[];
} & ManagePasskeysProps &
    ManageTwoFactorProps;

export default function Security(props: Props) {
    const passwordInput = useRef<HTMLInputElement>(null);
    const currentPasswordInput = useRef<HTMLInputElement>(null);

    return (
        <>
            <Head title="Security settings" />

            <PageHeader
                title="Security"
                description="Password, two-factor authentication, passkeys, and active sessions."
            />

            <div className="space-y-6">
                <Heading
                    title="Update password"
                    description="Ensure your account is using a long, random password to stay secure"
                />

                <Form
                    {...SecurityController.update.form()}
                    options={{
                        preserveScroll: true,
                    }}
                    resetOnError={[
                        'password',
                        'password_confirmation',
                        'current_password',
                    ]}
                    resetOnSuccess
                    onError={(errors) => {
                        if (errors.password) {
                            passwordInput.current?.focus();
                        }

                        if (errors.current_password) {
                            currentPasswordInput.current?.focus();
                        }
                    }}
                    className="space-y-6"
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="current_password">
                                    Current password
                                </Label>

                                <PasswordInput
                                    id="current_password"
                                    ref={currentPasswordInput}
                                    name="current_password"
                                    className="mt-1 block w-full"
                                    autoComplete="current-password"
                                    placeholder="Current password"
                                />

                                {errors.current_password && (
                                    <FieldError>
                                        {errors.current_password}
                                    </FieldError>
                                )}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password">New password</Label>

                                <PasswordInput
                                    id="password"
                                    ref={passwordInput}
                                    name="password"
                                    className="mt-1 block w-full"
                                    autoComplete="new-password"
                                    placeholder="New password"
                                    passwordrules={props.passwordRules}
                                />

                                {errors.password && (
                                    <FieldError>{errors.password}</FieldError>
                                )}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password_confirmation">
                                    Confirm password
                                </Label>

                                <PasswordInput
                                    id="password_confirmation"
                                    name="password_confirmation"
                                    className="mt-1 block w-full"
                                    autoComplete="new-password"
                                    placeholder="Confirm password"
                                    passwordrules={props.passwordRules}
                                />

                                {errors.password_confirmation && (
                                    <FieldError>
                                        {errors.password_confirmation}
                                    </FieldError>
                                )}
                            </div>

                            <div className="flex items-center gap-4">
                                <Button
                                    disabled={processing}
                                    data-test="update-password-button"
                                >
                                    Save
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Sessions</CardTitle>
                    <CardDescription>
                        Devices currently signed in to this account. LinkPilot
                        never shows IP addresses here.
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                    <ul className="divide-y rounded-md border">
                        {props.sessions.map((session) => (
                            <li
                                key={session.id}
                                className="flex flex-wrap items-center justify-between gap-2 p-3 text-sm"
                            >
                                <div>
                                    <p className="flex items-center gap-2 font-medium">
                                        {session.device}
                                        {session.isCurrent && (
                                            <Badge variant="secondary">
                                                This device
                                            </Badge>
                                        )}
                                    </p>
                                    <p className="text-muted-foreground">
                                        Last active{' '}
                                        {new Date(
                                            session.lastActivity,
                                        ).toLocaleString()}
                                    </p>
                                </div>
                            </li>
                        ))}
                    </ul>

                    <div className="flex flex-wrap gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() =>
                                router.delete(
                                    '/account-settings/sessions/others',
                                    { preserveScroll: true },
                                )
                            }
                        >
                            Sign out other sessions
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() =>
                                router.delete('/account-settings/api-tokens', {
                                    preserveScroll: true,
                                })
                            }
                        >
                            Revoke all API tokens
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <ManageTwoFactor
                canManageTwoFactor={props.canManageTwoFactor}
                requiresConfirmation={props.requiresConfirmation}
                twoFactorEnabled={props.twoFactorEnabled}
            />

            <ManagePasskeys
                canManagePasskeys={props.canManagePasskeys}
                passkeys={props.passkeys}
            />
        </>
    );
}

Security.layout = {
    breadcrumbs: [
        {
            title: 'Security settings',
            href: edit(),
        },
    ],
};
