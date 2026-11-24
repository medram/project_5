import { useForm } from '@inertiajs/react';
import { type FormEvent, type ReactNode, useState } from 'react';
import { useContentEditorRoutes } from '@/components/admin/content-editor-routes';
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
    listQuery: string;
    children?: ReactNode;
    open?: boolean;
    onOpenChange?: (open: boolean) => void;
};

function dataFor(folder?: FolderPayload) {
    return { name: folder?.name ?? '' };
}

function withListQuery(url: string, listQuery: string): string {
    return listQuery === '' ? url : `${url}?${listQuery}`;
}
export function FolderFormDialog({
    folder,
    listQuery,
    children,
    open: controlledOpen,
    onOpenChange,
}: FolderFormDialogProps) {
    const [uncontrolledOpen, setUncontrolledOpen] = useState(false);
    const open = controlledOpen ?? uncontrolledOpen;
    const { editorUrl } = useContentEditorRoutes();
    const form = useForm(dataFor(folder));
    const isEditing = folder !== undefined;

    function handleOpenChange(nextOpen: boolean) {
        if (form.processing) {
            return;
        }

        setUncontrolledOpen(nextOpen);
        onOpenChange?.(nextOpen);

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
                setUncontrolledOpen(false);
                onOpenChange?.(false);
                form.reset();
            },
        };

        if (folder) {
            form.patch(
                editorUrl(withListQuery(update(folder.id).url, listQuery)),
                options,
            );

            return;
        }

        form.post(withListQuery(store().url, listQuery), options);
    }

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            {children && <DialogTrigger asChild>{children}</DialogTrigger>}
            <DialogContent
                onOpenAutoFocus={(event) => {
                    if (window.matchMedia('(max-width: 640px)').matches) {
                        event.preventDefault();
                        document
                            .getElementById('folder-form-dialog-title')
                            ?.focus();
                    }
                }}
            >
                <DialogHeader>
                    <DialogTitle id="folder-form-dialog-title" tabIndex={-1}>
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
