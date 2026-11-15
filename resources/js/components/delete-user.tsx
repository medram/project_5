import { Form } from '@inertiajs/react';
import { useRef } from 'react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';

export default function DeleteUser() {
    const passwordInput = useRef<HTMLInputElement>(null);

    return (
        <div className="space-y-6">
            <Heading
                title="Schedule account deletion"
                variant="small"
                description="Schedule deletion with a 30-day restore window. Transfer every collaborative workspace and cancel any paid workspace subscription first."
            />
            <div className="space-y-4 rounded-xl border bg-card p-5">
                <div className="space-y-0.5">
                    <p className="font-medium">
                        Permanently removed after 30 days
                    </p>
                    <p className="text-sm text-muted-foreground">
                        Sign in before the window ends to restore the account.
                    </p>
                </div>

                <Dialog>
                    <DialogTrigger asChild>
                        <Button
                            variant="outline"
                            className="text-destructive"
                            data-test="delete-user-button"
                        >
                            Schedule deletion
                        </Button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogTitle>
                            Schedule your account for deletion?
                        </DialogTitle>
                        <DialogDescription>
                            Your account is disabled for 30 days and can be
                            restored by signing in. After that window every
                            workspace you own alone and its resources are
                            permanently removed. Ownership of a workspace with
                            other members must be transferred first. Resources
                            you created in shared workspaces are retained; only
                            your creator attribution is cleared.
                        </DialogDescription>

                        <Form
                            {...ProfileController.destroy.form()}
                            options={{
                                preserveScroll: true,
                            }}
                            onError={() => passwordInput.current?.focus()}
                            resetOnSuccess
                            className="space-y-6"
                        >
                            {({ resetAndClearErrors, processing, errors }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label
                                            htmlFor="password"
                                            className="sr-only"
                                        >
                                            Password
                                        </Label>

                                        <PasswordInput
                                            id="password"
                                            name="password"
                                            ref={passwordInput}
                                            placeholder="Password"
                                            autoComplete="current-password"
                                        />

                                        <InputError message={errors.password} />
                                    </div>
                                    <InputError message={errors.workspaces} />

                                    <DialogFooter className="gap-2">
                                        <DialogClose asChild>
                                            <Button
                                                variant="secondary"
                                                onClick={() =>
                                                    resetAndClearErrors()
                                                }
                                            >
                                                Cancel
                                            </Button>
                                        </DialogClose>

                                        <Button
                                            variant="destructive"
                                            disabled={processing}
                                            asChild
                                        >
                                            <button
                                                type="submit"
                                                data-test="confirm-delete-user-button"
                                            >
                                                Schedule deletion
                                            </button>
                                        </Button>
                                    </DialogFooter>
                                </>
                            )}
                        </Form>
                    </DialogContent>
                </Dialog>
            </div>
        </div>
    );
}
