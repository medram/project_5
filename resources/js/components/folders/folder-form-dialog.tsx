import { useForm } from '@inertiajs/react';
import { type FormEvent, type ReactNode, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import {
    Field,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { store, update } from '@/routes/folders';
import type { FolderPayload } from '@/types/folders';

type FolderFormDialogProps = {
    folder?: FolderPayload;
    children: ReactNode;
};

function dataFor(folder?: FolderPayload) {
    return { name: folder?.name ?? '' };
}

export function FolderFormDialog({
    folder,
    children,
}: FolderFormDialogProps) {
    const [open, setOpen] = useState(false);
    const form = useForm(dataFor(folder));
    const isEditing = folder !== undefined;

    function handleOpenChange(nextOpen: boolean) {
        if (form.processing) {
            return;
        }

        setOpen(nextOpen);

        if (nextOpen) {
            form.clearErrors();
            form.setData(dataFor(folder));
        }
    }

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        const options = {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
                form.reset();
            },
        };

        if (folder) {
            form.patch(update(folder.id).url, options);

            return;
        }

        form.post(store().url, options);
    }

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogTrigger asChild>{children}</DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {isEditing ? 'Rename folder' : 'Create folder'}
                    </DialogTitle>
                    <DialogDescription>
                        {isEditing
                            ? 'Choose a clearer name for this collection.'
                            : 'Group related short links for faster management.'}
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit}>
                    <FieldGroup>
                        <Field data-invalid={Boolean(form.errors.name)}>
                            <FieldLabel htmlFor="folder-name">Name</FieldLabel>
                            <Input
                                id="folder-name"
                                value={form.data.name}
                                onChange={(event) =>
                                    form.setData('name', event.target.value)
                                }
                                placeholder="Product launches"
                                maxLength={80}
                                autoComplete="off"
                                autoFocus
                                disabled={form.processing}
                                aria-invalid={Boolean(form.errors.name)}
                            />
                            {form.errors.name && (
                                <FieldError>{form.errors.name}</FieldError>
                            )}
                        </Field>
                        <DialogFooter>
                            <DialogClose asChild>
                                <Button
                                    type="button"
                                    variant="outline"
                                    disabled={form.processing}
                                >
                                    Cancel
                                </Button>
                            </DialogClose>
                            <Button type="submit" disabled={form.processing}>
                                {form.processing && (
                                    <Spinner data-icon="inline-start" />
                                )}
                                {isEditing ? 'Save folder' : 'Create folder'}
                            </Button>
                        </DialogFooter>
                    </FieldGroup>
                </form>
            </DialogContent>
        </Dialog>
    );
}
