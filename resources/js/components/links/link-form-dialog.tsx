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
    FieldDescription,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { store, update } from '@/routes/links';
import type { LinkListItem } from '@/types/links';

type LinkFormData = {
    destination_url: string;
    title: string;
    slug: string;
    is_active: boolean;
};

type LinkFormDialogProps = {
    link?: LinkListItem;
    children: ReactNode;
};

function formDataFor(link?: LinkListItem): LinkFormData {
    return {
        destination_url: link?.destinationUrl ?? '',
        title: link?.title ?? '',
        slug: link?.slug ?? '',
        is_active: link?.isActive ?? true,
    };
}

export function LinkFormDialog({ link, children }: LinkFormDialogProps) {
    const [open, setOpen] = useState(false);
    const form = useForm<LinkFormData>(formDataFor(link));
    const isEditing = link !== undefined;

    function handleOpenChange(nextOpen: boolean) {
        if (form.processing) {
            return;
        }

        setOpen(nextOpen);

        if (nextOpen) {
            form.clearErrors();
            form.setData(formDataFor(link));
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

        if (link) {
            form.patch(update(link.id).url, options);

            return;
        }

        form.post(store().url, options);
    }

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogTrigger asChild>{children}</DialogTrigger>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>
                        {isEditing ? 'Edit short link' : 'Create short link'}
                    </DialogTitle>
                    <DialogDescription>
                        {isEditing
                            ? 'Update where this link sends visitors.'
                            : 'Add a destination and publish a route when you are ready.'}
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={submit}>
                    <FieldGroup>
                        <Field
                            data-invalid={Boolean(form.errors.destination_url)}
                        >
                            <FieldLabel htmlFor="destination-url">
                                Destination URL
                            </FieldLabel>
                            <Input
                                id="destination-url"
                                type="url"
                                value={form.data.destination_url}
                                onChange={(event) =>
                                    form.setData(
                                        'destination_url',
                                        event.target.value,
                                    )
                                }
                                placeholder="https://example.com/launch"
                                maxLength={2048}
                                autoComplete="url"
                                autoFocus
                                disabled={form.processing}
                                aria-invalid={Boolean(
                                    form.errors.destination_url,
                                )}
                            />
                            {form.errors.destination_url && (
                                <FieldError>
                                    {form.errors.destination_url}
                                </FieldError>
                            )}
                        </Field>

                        <Field data-invalid={Boolean(form.errors.title)}>
                            <FieldLabel htmlFor="link-title">Title</FieldLabel>
                            <Input
                                id="link-title"
                                value={form.data.title}
                                onChange={(event) =>
                                    form.setData('title', event.target.value)
                                }
                                placeholder="Spring campaign"
                                maxLength={160}
                                autoComplete="off"
                                disabled={form.processing}
                                aria-invalid={Boolean(form.errors.title)}
                            />
                            {form.errors.title && (
                                <FieldError>{form.errors.title}</FieldError>
                            )}
                        </Field>

                        <Field data-invalid={Boolean(form.errors.slug)}>
                            <FieldLabel htmlFor="custom-slug">
                                Custom slug
                            </FieldLabel>
                            <Input
                                id="custom-slug"
                                value={form.data.slug}
                                onChange={(event) =>
                                    form.setData('slug', event.target.value)
                                }
                                placeholder="spring-launch"
                                maxLength={32}
                                autoComplete="off"
                                required={isEditing}
                                disabled={form.processing}
                                aria-invalid={Boolean(form.errors.slug)}
                            />
                            <FieldDescription>
                                {isEditing
                                    ? 'Required for existing links. Use 4–32 lowercase letters, numbers, or hyphens.'
                                    : 'Optional. Use 4–32 lowercase letters, numbers, or hyphens.'}
                            </FieldDescription>
                            {form.errors.slug && (
                                <FieldError>{form.errors.slug}</FieldError>
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
                                {isEditing ? 'Save changes' : 'Create link'}
                            </Button>
                        </DialogFooter>
                    </FieldGroup>
                </form>
            </DialogContent>
        </Dialog>
    );
}
