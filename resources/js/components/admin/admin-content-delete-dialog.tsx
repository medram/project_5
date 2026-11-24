import { useForm } from '@inertiajs/react';
import { type FormEvent, type ReactNode, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Field, FieldError, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';

type AdminContentDeleteDialogProps = {
    deleteUrl: string;
    resourceLabel: string;
    recordName: string;
    description: string;
    confirmationValue?: string | null;
    children: ReactNode;
};

export function AdminContentDeleteDialog({
    deleteUrl,
    resourceLabel,
    description,
    confirmationValue,
    children,
}: AdminContentDeleteDialogProps) {
    const [open, setOpen] = useState(false);
    const form = useForm<{ confirmation: string }>({ confirmation: '' });
    const requiresConfirmation =
        confirmationValue !== null && confirmationValue !== undefined;
    const confirmationMatches =
        !requiresConfirmation ||
        form.data.confirmation.trim() === confirmationValue;
    const otherError = Object.entries(form.errors).find(
        ([key]) => key !== 'confirmation',
    )?.[1];

    function changeOpen(next: boolean) {
        if (form.processing) {
            return;
        }

        setOpen(next);
        if (!next) {
            form.setData('confirmation', '');
            form.clearErrors();
        }
    }

    function submit(event: FormEvent) {
        event.preventDefault();
        if (form.processing || !confirmationMatches) {
            return;
        }

        form.delete(deleteUrl, {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    }

    return (
        <Dialog open={open} onOpenChange={changeOpen}>
            <DialogTrigger asChild>{children}</DialogTrigger>
            <DialogContent>
                <form onSubmit={submit} className="grid gap-5">
                    <DialogHeader>
                        <DialogTitle>Delete this {resourceLabel}?</DialogTitle>
                        <DialogDescription>{description}</DialogDescription>
                    </DialogHeader>
                    {requiresConfirmation && (
                        <Field>
                            <FieldLabel htmlFor="admin-content-delete-confirmation">
                                Type {confirmationValue} to confirm
                            </FieldLabel>
                            <Input
                                id="admin-content-delete-confirmation"
                                value={form.data.confirmation}
                                autoComplete="off"
                                spellCheck={false}
                                aria-invalid={
                                    form.errors.confirmation ? true : undefined
                                }
                                aria-describedby={
                                    form.errors.confirmation
                                        ? 'admin-content-delete-confirmation-error'
                                        : undefined
                                }
                                onChange={(event) =>
                                    form.setData(
                                        'confirmation',
                                        event.target.value,
                                    )
                                }
                            />
                            <FieldError
                                id="admin-content-delete-confirmation-error"
                                role="alert"
                            >
                                {form.errors.confirmation}
                            </FieldError>
                        </Field>
                    )}
                    {!requiresConfirmation && form.errors.confirmation && (
                        <FieldError role="alert">
                            {form.errors.confirmation}
                        </FieldError>
                    )}
                    {otherError && (
                        <FieldError role="alert">{otherError}</FieldError>
                    )}
                    <DialogFooter className="gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            disabled={form.processing}
                            onClick={() => changeOpen(false)}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            variant="destructive"
                            disabled={form.processing || !confirmationMatches}
                        >
                            {form.processing
                                ? 'Deleting…'
                                : `Delete ${resourceLabel}`}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
