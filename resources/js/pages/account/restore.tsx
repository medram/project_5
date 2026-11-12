import { Form, Head, Link } from '@inertiajs/react';
import { useRef } from 'react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';

type Props = {
    purgeAfter: string | null;
};

export default function RestoreAccount({ purgeAfter }: Props) {
    const passwordInput = useRef<HTMLInputElement>(null);

    return (
        <>
            <Head title="Restore account" />

            <div className="mx-auto flex w-full max-w-xl flex-col gap-6 p-4 sm:p-6">
                <div className="space-y-2">
                    <h1 className="text-xl font-semibold">Restore account</h1>
                    <p className="text-sm text-muted-foreground">
                        This account is scheduled for deletion
                        {purgeAfter
                            ? ` on ${new Date(purgeAfter).toLocaleDateString()}`
                            : ''}
                        . Restoring clears the deletion schedule and keeps all
                        current data.
                    </p>
                </div>

                <Form
                    action="/account-settings/restore"
                    method="post"
                    resetOnSuccess
                    onError={() => passwordInput.current?.focus()}
                    className="space-y-4 rounded-lg border p-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="password">
                                    Confirm your password
                                </Label>
                                <PasswordInput
                                    id="password"
                                    name="password"
                                    ref={passwordInput}
                                    autoComplete="current-password"
                                    required
                                />
                                <InputError message={errors.password} />
                            </div>

                            <Button disabled={processing}>
                                Restore my account
                            </Button>
                        </>
                    )}
                </Form>

                <Link
                    href="/logout"
                    method="post"
                    as="button"
                    className="self-start text-sm text-muted-foreground underline"
                >
                    Log out instead
                </Link>
            </div>
        </>
    );
}
