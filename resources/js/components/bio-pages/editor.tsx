import { router, useForm } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowUp,
    ExternalLink,
    Pencil,
    Plus,
    Power,
    Trash2,
} from 'lucide-react';
import { type FormEvent, type ReactNode, useState } from 'react';
import { LivePreview } from '@/components/bio-pages/live-preview';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
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
import { Textarea } from '@/components/ui/textarea';
import { publicMethod, update as updateBioPage } from '@/routes/bio-pages';
import {
    destroy as destroyBioLink,
    reorder,
    store as storeBioLink,
    update as updateBioLink,
} from '@/routes/bio-pages/links';
import {
    type BioLink,
    type BioPage,
    type BioPageEditorProps,
    toBioLink,
    toBioPage,
} from '@/types/bio-pages';

type BioPageFormData = {
    handle: string;
    display_name: string;
    bio: string;
    is_published: boolean;
};

type BioLinkFormData = {
    title: string;
    destination_url: string;
    is_active: boolean;
};

type BioLinkFormDialogProps = {
    bioPage: BioPage;
    bioLink?: BioLink;
    children: ReactNode;
};

function formDataForPage(bioPage: BioPage): BioPageFormData {
    return {
        handle: bioPage.handle,
        display_name: bioPage.displayName,
        bio: bioPage.bio ?? '',
        is_published: bioPage.isPublished,
    };
}

function formDataForBioLink(bioLink?: BioLink): BioLinkFormData {
    return {
        title: bioLink?.title ?? '',
        destination_url: bioLink?.destinationUrl ?? '',
        is_active: bioLink?.isActive ?? true,
    };
}

function BioLinkFormDialog({
    bioPage,
    bioLink,
    children,
}: BioLinkFormDialogProps) {
    const [open, setOpen] = useState(false);
    const form = useForm<BioLinkFormData>(formDataForBioLink(bioLink));
    const isEditing = bioLink !== undefined;
    const formId = bioLink?.id ?? 'new';

    function handleOpenChange(nextOpen: boolean) {
        if (form.processing) {
            return;
        }

        setOpen(nextOpen);

        if (nextOpen) {
            form.clearErrors();
            form.setData(formDataForBioLink(bioLink));
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

        if (bioLink) {
            form.patch(
                updateBioLink({
                    bioPage: bioPage.id,
                    bioLink: bioLink.id,
                }).url,
                options,
            );

            return;
        }

        form.post(storeBioLink(bioPage.id).url, options);
    }

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogTrigger asChild>{children}</DialogTrigger>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>
                        {isEditing ? 'Edit button' : 'Add button'}
                    </DialogTitle>
                    <DialogDescription>
                        {isEditing
                            ? 'Change where this button sends a visitor.'
                            : 'Name a destination for this public page.'}
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={submit}>
                    <FieldGroup>
                        <Field data-invalid={Boolean(form.errors.title)}>
                            <FieldLabel htmlFor={`bio-link-title-${formId}`}>
                                Button label
                            </FieldLabel>
                            <Input
                                id={`bio-link-title-${formId}`}
                                value={form.data.title}
                                onChange={(event) =>
                                    form.setData('title', event.target.value)
                                }
                                placeholder="Portfolio"
                                maxLength={100}
                                autoComplete="off"
                                autoFocus
                                disabled={form.processing}
                                aria-invalid={Boolean(form.errors.title)}
                            />
                            {form.errors.title && (
                                <FieldError>{form.errors.title}</FieldError>
                            )}
                        </Field>

                        <Field
                            data-invalid={Boolean(form.errors.destination_url)}
                        >
                            <FieldLabel
                                htmlFor={`bio-link-destination-${formId}`}
                            >
                                Destination URL
                            </FieldLabel>
                            <Input
                                id={`bio-link-destination-${formId}`}
                                type="url"
                                value={form.data.destination_url}
                                onChange={(event) =>
                                    form.setData(
                                        'destination_url',
                                        event.target.value,
                                    )
                                }
                                placeholder="https://example.com/portfolio"
                                maxLength={2048}
                                autoComplete="url"
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

                        <Field className="flex-row items-start gap-3">
                            <Checkbox
                                id={`bio-link-active-${formId}`}
                                checked={form.data.is_active}
                                onCheckedChange={(checked) =>
                                    form.setData('is_active', checked === true)
                                }
                                disabled={form.processing}
                            />
                            <div className="flex min-w-0 flex-col gap-1">
                                <FieldLabel
                                    htmlFor={`bio-link-active-${formId}`}
                                >
                                    Show this button publicly
                                </FieldLabel>
                                <FieldDescription>
                                    Disabled buttons remain in the editor but
                                    stay hidden from visitors.
                                </FieldDescription>
                            </div>
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
                                {isEditing ? 'Save button' : 'Add button'}
                            </Button>
                        </DialogFooter>
                    </FieldGroup>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function DeleteBioLinkDialog({
    bioPage,
    bioLink,
}: {
    bioPage: BioPage;
    bioLink: BioLink;
}) {
    const [open, setOpen] = useState(false);
    const [processing, setProcessing] = useState(false);

    function deleteBioLink() {
        router.delete(
            destroyBioLink({
                bioPage: bioPage.id,
                bioLink: bioLink.id,
            }).url,
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onSuccess: () => setOpen(false),
                onFinish: () => setProcessing(false),
            },
        );
    }

    return (
        <Dialog
            open={open}
            onOpenChange={(nextOpen) => {
                if (!processing) {
                    setOpen(nextOpen);
                }
            }}
        >
            <DialogTrigger asChild>
                <Button variant="destructive" size="sm">
                    <Trash2 data-icon="inline-start" />
                    Delete
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete this button?</DialogTitle>
                    <DialogDescription>
                        {bioLink.title} will no longer appear on this bio page.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <DialogClose asChild>
                        <Button
                            type="button"
                            variant="outline"
                            disabled={processing}
                        >
                            Cancel
                        </Button>
                    </DialogClose>
                    <Button
                        type="button"
                        variant="destructive"
                        onClick={deleteBioLink}
                        disabled={processing}
                    >
                        {processing && <Spinner data-icon="inline-start" />}
                        Delete button
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function BioLinkStatusAction({
    bioPage,
    bioLink,
}: {
    bioPage: BioPage;
    bioLink: BioLink;
}) {
    const [processing, setProcessing] = useState(false);

    function toggleStatus() {
        router.patch(
            updateBioLink({
                bioPage: bioPage.id,
                bioLink: bioLink.id,
            }).url,
            {
                title: bioLink.title,
                destination_url: bioLink.destinationUrl,
                is_active: !bioLink.isActive,
            },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
            },
        );
    }

    return (
        <Button
            type="button"
            variant="outline"
            size="sm"
            onClick={toggleStatus}
            disabled={processing}
        >
            {processing ? (
                <Spinner data-icon="inline-start" />
            ) : (
                <Power data-icon="inline-start" />
            )}
            {bioLink.isActive ? 'Disable' : 'Enable'}
        </Button>
    );
}

function BioLinkCard({
    bioPage,
    bioLink,
    index,
    total,
    reordering,
    onMove,
}: {
    bioPage: BioPage;
    bioLink: BioLink;
    index: number;
    total: number;
    reordering: boolean;
    onMove: (linkId: number, direction: 'up' | 'down') => void;
}) {
    return (
        <Card>
            <CardHeader className="gap-3">
                <div className="flex min-w-0 flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                    <div className="flex min-w-0 flex-col gap-1">
                        <div className="flex flex-wrap items-center gap-2">
                            <CardTitle>{bioLink.title}</CardTitle>
                            <Badge
                                variant={
                                    bioLink.isActive ? 'default' : 'outline'
                                }
                            >
                                {bioLink.isActive ? 'Active' : 'Disabled'}
                            </Badge>
                        </div>
                        <CardDescription className="break-words">
                            {bioLink.destinationUrl}
                        </CardDescription>
                    </div>
                    <p className="shrink-0 text-sm text-muted-foreground">
                        Button {index + 1}
                    </p>
                </div>
            </CardHeader>
            <CardContent className="flex flex-wrap gap-2">
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={() => onMove(bioLink.id, 'up')}
                    disabled={index === 0 || reordering}
                >
                    {reordering ? (
                        <Spinner data-icon="inline-start" />
                    ) : (
                        <ArrowUp data-icon="inline-start" />
                    )}
                    Move up
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={() => onMove(bioLink.id, 'down')}
                    disabled={index === total - 1 || reordering}
                >
                    {reordering ? (
                        <Spinner data-icon="inline-start" />
                    ) : (
                        <ArrowDown data-icon="inline-start" />
                    )}
                    Move down
                </Button>
            </CardContent>
            <CardFooter className="flex flex-wrap gap-2">
                <BioLinkFormDialog bioPage={bioPage} bioLink={bioLink}>
                    <Button variant="outline" size="sm">
                        <Pencil data-icon="inline-start" />
                        Edit
                    </Button>
                </BioLinkFormDialog>
                <BioLinkStatusAction bioPage={bioPage} bioLink={bioLink} />
                <DeleteBioLinkDialog bioPage={bioPage} bioLink={bioLink} />
            </CardFooter>
        </Card>
    );
}

export function BioPageEditor({
    bioPage: bioPagePayload,
    bioLinks: bioLinkPayloads,
}: BioPageEditorProps) {
    const bioPage = toBioPage(bioPagePayload);
    const bioLinks = bioLinkPayloads.map(toBioLink);
    const form = useForm<BioPageFormData>(formDataForPage(bioPage));
    const [reorderingId, setReorderingId] = useState<number | null>(null);
    const publicUrl = publicMethod(bioPage.handle).url;
    const previewLinks = bioLinks
        .filter((bioLink) => bioLink.isActive)
        .map((bioLink) => ({
            id: bioLink.id,
            title: bioLink.title,
            destinationUrl: bioLink.destinationUrl,
        }));

    function submitProfile(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        form.patch(updateBioPage(bioPage.id).url, {
            preserveScroll: true,
        });
    }

    function moveBioLink(linkId: number, direction: 'up' | 'down') {
        const currentIndex = bioLinks.findIndex(
            (bioLink) => bioLink.id === linkId,
        );
        const nextIndex =
            direction === 'up' ? currentIndex - 1 : currentIndex + 1;

        if (currentIndex < 0 || nextIndex < 0 || nextIndex >= bioLinks.length) {
            return;
        }

        const orderedIds = bioLinks.map((bioLink) => bioLink.id);
        [orderedIds[currentIndex], orderedIds[nextIndex]] = [
            orderedIds[nextIndex],
            orderedIds[currentIndex],
        ];

        router.patch(
            reorder(bioPage.id).url,
            { link_ids: orderedIds },
            {
                preserveScroll: true,
                onStart: () => setReorderingId(linkId),
                onFinish: () => setReorderingId(null),
            },
        );
    }

    return (
        <div className="grid min-w-0 gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(20rem,28rem)] xl:items-start">
            <div className="flex min-w-0 flex-col gap-6">
                <form onSubmit={submitProfile}>
                    <Card>
                        <CardHeader>
                            <CardTitle>Profile details</CardTitle>
                            <CardDescription>
                                Set the identity and address visitors will see.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <FieldGroup>
                                <Field
                                    data-invalid={Boolean(form.errors.handle)}
                                >
                                    <FieldLabel htmlFor="bio-page-handle">
                                        Handle
                                    </FieldLabel>
                                    <Input
                                        id="bio-page-handle"
                                        value={form.data.handle}
                                        onChange={(event) =>
                                            form.setData(
                                                'handle',
                                                event.target.value.toLowerCase(),
                                            )
                                        }
                                        placeholder="your-name"
                                        maxLength={32}
                                        autoComplete="off"
                                        disabled={form.processing}
                                        aria-invalid={Boolean(
                                            form.errors.handle,
                                        )}
                                    />
                                    <FieldDescription>
                                        Use 3–32 lowercase letters, numbers, or
                                        hyphens.
                                    </FieldDescription>
                                    {form.errors.handle && (
                                        <FieldError>
                                            {form.errors.handle}
                                        </FieldError>
                                    )}
                                </Field>

                                <Field
                                    data-invalid={Boolean(
                                        form.errors.display_name,
                                    )}
                                >
                                    <FieldLabel htmlFor="bio-page-display-name">
                                        Display name
                                    </FieldLabel>
                                    <Input
                                        id="bio-page-display-name"
                                        value={form.data.display_name}
                                        onChange={(event) =>
                                            form.setData(
                                                'display_name',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="Your name or brand"
                                        maxLength={80}
                                        autoComplete="name"
                                        disabled={form.processing}
                                        aria-invalid={Boolean(
                                            form.errors.display_name,
                                        )}
                                    />
                                    {form.errors.display_name && (
                                        <FieldError>
                                            {form.errors.display_name}
                                        </FieldError>
                                    )}
                                </Field>

                                <Field data-invalid={Boolean(form.errors.bio)}>
                                    <FieldLabel htmlFor="bio-page-bio">
                                        Bio
                                    </FieldLabel>
                                    <Textarea
                                        id="bio-page-bio"
                                        value={form.data.bio}
                                        onChange={(event) =>
                                            form.setData(
                                                'bio',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="A short introduction for visitors."
                                        maxLength={280}
                                        rows={4}
                                        disabled={form.processing}
                                        aria-invalid={Boolean(form.errors.bio)}
                                    />
                                    <FieldDescription>
                                        Optional. Up to 280 characters.
                                    </FieldDescription>
                                    {form.errors.bio && (
                                        <FieldError>
                                            {form.errors.bio}
                                        </FieldError>
                                    )}
                                </Field>

                                <Field className="flex-row items-start gap-3">
                                    <Checkbox
                                        id="bio-page-published"
                                        checked={form.data.is_published}
                                        onCheckedChange={(checked) =>
                                            form.setData(
                                                'is_published',
                                                checked === true,
                                            )
                                        }
                                        disabled={form.processing}
                                    />
                                    <div className="flex min-w-0 flex-col gap-1">
                                        <FieldLabel htmlFor="bio-page-published">
                                            Publish this page
                                        </FieldLabel>
                                        <FieldDescription>
                                            Published pages are visible at the
                                            public address below. Drafts stay
                                            private.
                                        </FieldDescription>
                                    </div>
                                </Field>
                            </FieldGroup>
                        </CardContent>
                        <CardFooter className="flex flex-col items-stretch gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div className="min-w-0">
                                <p className="text-sm font-medium">
                                    Public address
                                </p>
                                {bioPage.isPublished ? (
                                    <a
                                        href={publicUrl}
                                        target="_blank"
                                        rel="noreferrer"
                                        className="flex w-fit max-w-full items-center gap-1 text-sm break-all text-primary underline-offset-4 hover:underline"
                                    >
                                        {publicUrl}
                                        <ExternalLink aria-hidden="true" />
                                        <span className="sr-only">
                                            Open public bio page
                                        </span>
                                    </a>
                                ) : (
                                    <p className="text-sm break-all text-muted-foreground">
                                        {publicUrl}
                                    </p>
                                )}
                                {!bioPage.isPublished && (
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        This address is reserved. Save a
                                        published page before sharing it.
                                    </p>
                                )}
                            </div>
                            <Button
                                type="submit"
                                disabled={form.processing}
                                className="shrink-0"
                            >
                                {form.processing && (
                                    <Spinner data-icon="inline-start" />
                                )}
                                {form.data.is_published
                                    ? 'Save and publish'
                                    : 'Save draft'}
                            </Button>
                        </CardFooter>
                    </Card>
                </form>

                <Card>
                    <CardHeader className="gap-4">
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                            <div className="flex flex-col gap-1.5">
                                <CardTitle>Buttons</CardTitle>
                                <CardDescription>
                                    Add destinations, control their visibility,
                                    and set their visitor order.
                                </CardDescription>
                            </div>
                            <BioLinkFormDialog bioPage={bioPage}>
                                <Button>
                                    <Plus data-icon="inline-start" />
                                    Add button
                                </Button>
                            </BioLinkFormDialog>
                        </div>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-3">
                        {bioLinks.length === 0 ? (
                            <p className="rounded-md border border-dashed p-4 text-sm leading-6 text-muted-foreground">
                                Add a button to give visitors somewhere to go.
                            </p>
                        ) : (
                            bioLinks.map((bioLink, index) => (
                                <BioLinkCard
                                    key={bioLink.id}
                                    bioPage={bioPage}
                                    bioLink={bioLink}
                                    index={index}
                                    total={bioLinks.length}
                                    reordering={reorderingId !== null}
                                    onMove={moveBioLink}
                                />
                            ))
                        )}
                    </CardContent>
                </Card>
            </div>

            <aside className="xl:sticky xl:top-6">
                <LivePreview
                    displayName={form.data.display_name}
                    bio={form.data.bio || null}
                    handle={form.data.handle}
                    links={previewLinks}
                    isPublished={bioPage.isPublished}
                />
            </aside>
        </div>
    );
}
