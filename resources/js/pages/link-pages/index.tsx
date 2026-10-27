import { Head, Link, router, useForm } from '@inertiajs/react';
import { ExternalLink, FilePenLine, Plus, Rocket, Trash2 } from 'lucide-react';
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
import { destroy, show, store } from '@/routes/link-pages';
import type { LinkPage, LinkPageIndexProps } from '@/types/link-pages';

type LinkPageFormData = {
    name: string;
    slug: string;
    title: string;
    description: string;
    is_published: boolean;
};

type LinkPageFieldsProps = {
    data: LinkPageFormData;
    errors: Partial<Record<keyof LinkPageFormData, string>>;
    processing: boolean;
    setData: (key: keyof LinkPageFormData, value: string | boolean) => void;
};

function LinkPageFields({
    data,
    errors,
    processing,
    setData,
}: LinkPageFieldsProps) {
    return (
        <FieldGroup>
            <Field data-invalid={Boolean(errors.name)}>
                <FieldLabel htmlFor="link-page-name">Internal name</FieldLabel>
                <Input
                    id="link-page-name"
                    value={data.name}
                    onChange={(event) => setData('name', event.target.value)}
                    placeholder="Spring launch"
                    maxLength={100}
                    disabled={processing}
                    aria-invalid={Boolean(errors.name)}
                />
                <FieldDescription>
                    This label is private to your account.
                </FieldDescription>
                {errors.name && <FieldError>{errors.name}</FieldError>}
            </Field>

            <Field data-invalid={Boolean(errors.slug)}>
                <FieldLabel htmlFor="link-page-slug">Public slug</FieldLabel>
                <Input
                    id="link-page-slug"
                    value={data.slug}
                    onChange={(event) =>
                        setData('slug', event.target.value.toLowerCase())
                    }
                    placeholder="spring-launch"
                    maxLength={32}
                    autoComplete="off"
                    disabled={processing}
                    aria-invalid={Boolean(errors.slug)}
                />
                <FieldDescription>
                    Three to 32 lowercase letters, numbers, or hyphens.
                </FieldDescription>
                {errors.slug && <FieldError>{errors.slug}</FieldError>}
            </Field>

            <Field data-invalid={Boolean(errors.title)}>
                <FieldLabel htmlFor="link-page-title">Public title</FieldLabel>
                <Input
                    id="link-page-title"
                    value={data.title}
                    onChange={(event) => setData('title', event.target.value)}
                    placeholder="Everything for the launch"
                    maxLength={160}
                    disabled={processing}
                    aria-invalid={Boolean(errors.title)}
                />
                {errors.title && <FieldError>{errors.title}</FieldError>}
            </Field>

            <Field data-invalid={Boolean(errors.description)}>
                <FieldLabel htmlFor="link-page-description">
                    Description
                </FieldLabel>
                <Textarea
                    id="link-page-description"
                    value={data.description}
                    onChange={(event) =>
                        setData('description', event.target.value)
                    }
                    placeholder="A short explanation for visitors."
                    maxLength={500}
                    disabled={processing}
                    aria-invalid={Boolean(errors.description)}
                />
                {errors.description && (
                    <FieldError>{errors.description}</FieldError>
                )}
            </Field>

            <Field className="flex-row items-center">
                <Checkbox
                    id="link-page-published"
                    checked={data.is_published}
                    onCheckedChange={(checked) =>
                        setData('is_published', checked === true)
                    }
                    disabled={processing}
                />
                <div className="grid gap-1.5 leading-none">
                    <FieldLabel htmlFor="link-page-published">
                        Publish immediately
                    </FieldLabel>
                    <FieldDescription>
                        Published pages are public at /pages/{data.slug || '…'}.
                    </FieldDescription>
                </div>
            </Field>
        </FieldGroup>
    );
}

function CreateLinkPageDialog({ children }: { children: ReactNode }) {
    const [open, setOpen] = useState(false);
    const form = useForm<LinkPageFormData>({
        name: '',
        slug: '',
        title: '',
        description: '',
        is_published: false,
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post(store().url, {
            onSuccess: () => {
                form.reset();
                setOpen(false);
            },
        });
    }

    const planError = (form.errors as Record<string, string | undefined>).plan;

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{children}</DialogTrigger>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Create link page</DialogTitle>
                    <DialogDescription>
                        Build a focused campaign page, then add destinations and
                        consent-gated pixels in the editor.
                    </DialogDescription>
                </DialogHeader>
                {planError && (
                    <p className="text-sm text-destructive">{planError}</p>
                )}
                <form onSubmit={submit}>
                    <LinkPageFields
                        data={form.data}
                        errors={form.errors}
                        processing={form.processing}
                        setData={form.setData}
                    />
                    <DialogFooter className="mt-6">
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
                </form>
            </DialogContent>
        </Dialog>
    );
}

function DeleteLinkPageDialog({ page }: { page: LinkPage }) {
    const [open, setOpen] = useState(false);
    const [processing, setProcessing] = useState(false);

    function remove() {
        router.delete(destroy(page).url, {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onSuccess: () => setOpen(false),
        });
    }

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button type="button" size="sm" variant="outline">
                    <Trash2 data-icon="inline-start" />
                    Remove
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Remove {page.name}?</DialogTitle>
                    <DialogDescription>
                        The public page and every destination on it will no
                        longer be available.
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
                        onClick={remove}
                        disabled={processing}
                    >
                        {processing && <Spinner data-icon="inline-start" />}
                        Remove page
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

export default function LinkPagesIndex({ linkPages }: LinkPageIndexProps) {
    return (
        <>
            <Head title="Link pages" />

            <div className="flex flex-col gap-6 p-4 sm:p-6">
                <PageHeader
                    title="Link pages"
                    eyebrow="Campaign destinations"
                    icon={Rocket}
                    description="Publish branded, workspace-owned destination pages for launches and campaigns."
                    actions={
                        <CreateLinkPageDialog>
                            <Button>
                                <Plus data-icon="inline-start" />
                                Create page
                            </Button>
                        </CreateLinkPageDialog>
                    }
                />

                {linkPages.length === 0 ? (
                    <Empty className="border">
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <FilePenLine />
                            </EmptyMedia>
                            <EmptyTitle>No link pages yet</EmptyTitle>
                            <EmptyDescription>
                                Start with a campaign, then add and order its
                                visitor destinations.
                            </EmptyDescription>
                        </EmptyHeader>
                        <EmptyContent>
                            <CreateLinkPageDialog>
                                <Button>
                                    <Plus data-icon="inline-start" />
                                    Create your first page
                                </Button>
                            </CreateLinkPageDialog>
                        </EmptyContent>
                    </Empty>
                ) : (
                    <div className="grid gap-4 lg:grid-cols-2">
                        {linkPages.map((page) => (
                            <Card key={page.id}>
                                <CardHeader>
                                    <div className="flex flex-wrap items-start justify-between gap-3">
                                        <div className="min-w-0 space-y-1">
                                            <CardTitle>{page.name}</CardTitle>
                                            <CardDescription className="break-all">
                                                /pages/{page.slug}
                                            </CardDescription>
                                        </div>
                                        <Badge
                                            variant={
                                                page.isPublished
                                                    ? 'default'
                                                    : 'secondary'
                                            }
                                        >
                                            {page.isPublished
                                                ? 'Published'
                                                : 'Draft'}
                                        </Badge>
                                    </div>
                                </CardHeader>
                                <CardContent className="space-y-2 text-sm text-muted-foreground">
                                    <p>{page.itemsCount} destinations</p>
                                </CardContent>
                                <CardFooter className="flex flex-wrap gap-2">
                                    <Button asChild size="sm">
                                        <Link href={show(page).url}>
                                            <FilePenLine data-icon="inline-start" />
                                            Edit
                                        </Link>
                                    </Button>
                                    {page.isPublished && (
                                        <Button
                                            asChild
                                            size="sm"
                                            variant="outline"
                                        >
                                            <a
                                                href={page.publicUrl}
                                                target="_blank"
                                                rel="noreferrer"
                                            >
                                                <ExternalLink data-icon="inline-start" />
                                                Visit
                                            </a>
                                        </Button>
                                    )}
                                    <DeleteLinkPageDialog page={page} />
                                </CardFooter>
                            </Card>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}
