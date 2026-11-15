import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    ExternalLink,
    FilePenLine,
    PanelsTopLeft,
    Plus,
    Power,
    Trash2,
} from 'lucide-react';
import { type FormEvent, type ReactNode, useState } from 'react';
import { PageHeader } from '@/components/page-header';
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
    Empty,
    EmptyContent,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
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
import {
    destroy,
    index,
    publicMethod,
    show,
    store,
    update,
} from '@/routes/bio-pages';
import {
    type BioPage,
    type BioPageIndexProps,
    toBioPage,
} from '@/types/bio-pages';

type BioPageFormData = {
    handle: string;
    display_name: string;
    bio: string;
    is_published: boolean;
};

type CreateBioPageDialogProps = {
    children: ReactNode;
};

function CreateBioPageDialog({ children }: CreateBioPageDialogProps) {
    const [open, setOpen] = useState(false);
    const form = useForm<BioPageFormData>({
        handle: '',
        display_name: '',
        bio: '',
        is_published: false,
    });

    function handleOpenChange(nextOpen: boolean) {
        if (form.processing) {
            return;
        }

        setOpen(nextOpen);

        if (nextOpen) {
            form.clearErrors();
            form.reset();
        }
    }

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        form.post(store().url, {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
                form.reset();
            },
        });
    }

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogTrigger asChild>{children}</DialogTrigger>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Create bio page</DialogTitle>
                    <DialogDescription>
                        Start a new public page for a project, persona, or
                        campaign.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit}>
                    <FieldGroup>
                        <Field data-invalid={Boolean(form.errors.handle)}>
                            <FieldLabel htmlFor="new-bio-page-handle">
                                Handle
                            </FieldLabel>
                            <Input
                                id="new-bio-page-handle"
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
                                autoFocus
                                disabled={form.processing}
                                aria-invalid={Boolean(form.errors.handle)}
                            />
                            <FieldDescription>
                                Use 3–32 lowercase letters, numbers, or hyphens.
                            </FieldDescription>
                            {form.errors.handle && (
                                <FieldError>{form.errors.handle}</FieldError>
                            )}
                        </Field>

                        <Field data-invalid={Boolean(form.errors.display_name)}>
                            <FieldLabel htmlFor="new-bio-page-display-name">
                                Display name
                            </FieldLabel>
                            <Input
                                id="new-bio-page-display-name"
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
                                aria-invalid={Boolean(form.errors.display_name)}
                            />
                            {form.errors.display_name && (
                                <FieldError>
                                    {form.errors.display_name}
                                </FieldError>
                            )}
                        </Field>

                        <Field data-invalid={Boolean(form.errors.bio)}>
                            <FieldLabel htmlFor="new-bio-page-bio">
                                Bio
                            </FieldLabel>
                            <Textarea
                                id="new-bio-page-bio"
                                value={form.data.bio}
                                onChange={(event) =>
                                    form.setData('bio', event.target.value)
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
                                <FieldError>{form.errors.bio}</FieldError>
                            )}
                        </Field>

                        <Field className="flex-row items-start gap-3">
                            <Checkbox
                                id="new-bio-page-published"
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
                                <FieldLabel htmlFor="new-bio-page-published">
                                    Publish immediately
                                </FieldLabel>
                                <FieldDescription>
                                    Leave this off to prepare the page before it
                                    goes live.
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
                                Create page
                            </Button>
                        </DialogFooter>
                    </FieldGroup>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function DeleteBioPageDialog({ bioPage }: { bioPage: BioPage }) {
    const [open, setOpen] = useState(false);
    const [processing, setProcessing] = useState(false);

    function deleteBioPage() {
        router.delete(destroy(bioPage.id).url, {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onSuccess: () => setOpen(false),
            onFinish: () => setProcessing(false),
        });
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
                    <DialogTitle>Delete this bio page?</DialogTitle>
                    <DialogDescription>
                        @{bioPage.handle} and all of its buttons will be removed
                        permanently.
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
                        onClick={deleteBioPage}
                        disabled={processing}
                    >
                        {processing && <Spinner data-icon="inline-start" />}
                        Delete page
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function BioPageActions({ bioPage }: { bioPage: BioPage }) {
    const [updatingStatus, setUpdatingStatus] = useState(false);

    function togglePublished() {
        router.patch(
            update(bioPage.id).url,
            {
                handle: bioPage.handle,
                display_name: bioPage.displayName,
                bio: bioPage.bio ?? '',
                is_published: !bioPage.isPublished,
            },
            {
                preserveScroll: true,
                onStart: () => setUpdatingStatus(true),
                onFinish: () => setUpdatingStatus(false),
            },
        );
    }

    return (
        <div className="flex flex-wrap gap-2">
            <Button variant="outline" size="sm" asChild>
                <Link href={show(bioPage.id)}>
                    <FilePenLine data-icon="inline-start" />
                    Edit
                </Link>
            </Button>
            <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={togglePublished}
                disabled={updatingStatus}
            >
                {updatingStatus ? (
                    <Spinner data-icon="inline-start" />
                ) : (
                    <Power data-icon="inline-start" />
                )}
                {bioPage.isPublished ? 'Unpublish' : 'Publish'}
            </Button>
            <DeleteBioPageDialog bioPage={bioPage} />
        </div>
    );
}

function BioPageRow({ bioPage }: { bioPage: BioPage }) {
    const publicUrl = publicMethod(bioPage.handle).url;

    return (
        <Card>
            <CardHeader className="gap-4">
                <div className="flex min-w-0 flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div className="flex min-w-0 flex-col gap-1">
                        <div className="flex flex-wrap items-center gap-2">
                            <CardTitle>{bioPage.displayName}</CardTitle>
                            <Badge
                                variant={
                                    bioPage.isPublished ? 'default' : 'outline'
                                }
                            >
                                {bioPage.isPublished ? 'Published' : 'Draft'}
                            </Badge>
                        </div>
                        <CardDescription className="break-words">
                            @{bioPage.handle}
                        </CardDescription>
                    </div>
                    {bioPage.isPublished && (
                        <a
                            href={publicUrl}
                            target="_blank"
                            rel="noreferrer"
                            className="flex w-fit items-center gap-1 text-sm font-medium text-primary underline-offset-4 hover:underline"
                        >
                            Open public page
                            <ExternalLink aria-hidden="true" />
                        </a>
                    )}
                </div>
            </CardHeader>
            <CardContent>
                <p className="max-w-2xl text-sm leading-6 break-words whitespace-pre-wrap text-muted-foreground">
                    {bioPage.bio || 'No bio yet.'}
                </p>
            </CardContent>
            <CardFooter className="flex-wrap gap-2">
                <BioPageActions bioPage={bioPage} />
            </CardFooter>
        </Card>
    );
}

export default function BioPages({
    bioPages: bioPagePayloads,
}: BioPageIndexProps) {
    const bioPages = bioPagePayloads.map(toBioPage);

    return (
        <>
            <Head title="Bio pages" />

            <section className="mx-auto flex w-full max-w-7xl min-w-0 flex-1 flex-col gap-5 px-5 pt-4 pb-10">
                <PageHeader
                    title="Bio pages"
                    description="Build a focused public profile and direct visitors to the destinations that matter."
                    actions={
                        <CreateBioPageDialog>
                            <Button>
                                <Plus data-icon="inline-start" />
                                Create bio page
                            </Button>
                        </CreateBioPageDialog>
                    }
                />

                {bioPages.length === 0 ? (
                    <Empty>
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <PanelsTopLeft aria-hidden="true" />
                            </EmptyMedia>
                            <EmptyTitle>No bio pages yet</EmptyTitle>
                            <EmptyDescription>
                                Create a page to collect your active
                                destinations behind one address.
                            </EmptyDescription>
                        </EmptyHeader>
                        <EmptyContent>
                            <CreateBioPageDialog>
                                <Button>
                                    <Plus data-icon="inline-start" />
                                    Create your first bio page
                                </Button>
                            </CreateBioPageDialog>
                        </EmptyContent>
                    </Empty>
                ) : (
                    <section
                        aria-label="Bio pages"
                        className="flex flex-col gap-3"
                    >
                        {bioPages.map((bioPage) => (
                            <BioPageRow key={bioPage.id} bioPage={bioPage} />
                        ))}
                    </section>
                )}
            </section>
        </>
    );
}

BioPages.layout = {
    breadcrumbs: [
        {
            title: 'Bio pages',
            href: index(),
        },
    ],
};
