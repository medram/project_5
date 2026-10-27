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
import { Field, FieldError, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import type { LinkTagOption } from '@/types/links';

type TagFormData = {
    name: string;
    color: string;
};

function withListQuery(url: string, listQuery: string): string {
    return listQuery === '' ? url : `${url}?${listQuery}`;
}

export function CreateLinkTagDialog({
    tags,
    listQuery,
    children,
}: {
    tags: LinkTagOption[];
    listQuery: string;
    children: ReactNode;
}) {
    const [open, setOpen] = useState(false);
    const form = useForm<TagFormData>({ name: '', color: '' });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post(withListQuery('/dashboard/link-tags', listQuery), {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
                form.reset();
            },
        });
    }

    return (
        <Dialog
            open={open}
            onOpenChange={(nextOpen) => {
                if (!form.processing) {
                    setOpen(nextOpen);
                    if (nextOpen) form.clearErrors();
                }
            }}
        >
            <DialogTrigger asChild>{children}</DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Create a link tag</DialogTitle>
                    <DialogDescription>
                        Organize links in this workspace with reusable labels.
                    </DialogDescription>
                </DialogHeader>
                <div
                    className="flex flex-wrap gap-2"
                    aria-label="Existing tags"
                >
                    {tags.map((tag) => (
                        <span
                            key={tag.id}
                            className="inline-flex min-h-8 items-center rounded-full border px-3 text-sm"
                            style={
                                tag.color
                                    ? { borderColor: tag.color }
                                    : undefined
                            }
                        >
                            {tag.name}
                        </span>
                    ))}
                    {tags.length === 0 && (
                        <p className="text-sm text-muted-foreground">
                            No tags yet.
                        </p>
                    )}
                </div>
                <form onSubmit={submit}>
                    <div className="flex flex-col gap-4">
                        <Field data-invalid={Boolean(form.errors.name)}>
                            <FieldLabel htmlFor="link-tag-name">
                                Name
                            </FieldLabel>
                            <Input
                                id="link-tag-name"
                                value={form.data.name}
                                onChange={(event) =>
                                    form.setData('name', event.target.value)
                                }
                                maxLength={80}
                                autoComplete="off"
                                required
                                disabled={form.processing}
                                aria-invalid={Boolean(form.errors.name)}
                            />
                            {form.errors.name && (
                                <FieldError>{form.errors.name}</FieldError>
                            )}
                        </Field>
                        <Field data-invalid={Boolean(form.errors.color)}>
                            <FieldLabel htmlFor="link-tag-color">
                                Color (optional)
                            </FieldLabel>
                            <Input
                                id="link-tag-color"
                                type="text"
                                value={form.data.color}
                                onChange={(event) =>
                                    form.setData('color', event.target.value)
                                }
                                placeholder="#2563EB"
                                pattern="^#[0-9a-fA-F]{6}$"
                                maxLength={7}
                                disabled={form.processing}
                                aria-invalid={Boolean(form.errors.color)}
                            />
                            {form.errors.color && (
                                <FieldError>{form.errors.color}</FieldError>
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
                            <Button
                                type="submit"
                                disabled={
                                    form.processing ||
                                    form.data.name.trim() === ''
                                }
                            >
                                {form.processing && (
                                    <Spinner data-icon="inline-start" />
                                )}
                                Create tag
                            </Button>
                        </DialogFooter>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}
