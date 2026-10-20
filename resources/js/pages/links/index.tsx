import { Head, Link, router } from '@inertiajs/react';
import {
    Activity,
    BarChart3,
    CalendarClock,
    ExternalLink,
    Folder,
    KeyRound,
    Link2,
    MousePointerClick,
    Pause,
    Pencil,
    Play,
    Plus,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import { LinkFormDialog } from '@/components/links/link-form-dialog';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import { Spinner } from '@/components/ui/spinner';
import { destroy, index, insights, update } from '@/routes/links';
import type { CustomDomainOption } from '@/types/custom-domains';
import type { FolderOption } from '@/types/folders';
import {
    type LinkIndexProps,
    type LinkListItem,
    toLinkListItem,
} from '@/types/links';

function DeleteLinkDialog({ link }: { link: LinkListItem }) {
    const [open, setOpen] = useState(false);
    const [processing, setProcessing] = useState(false);

    function deleteLink() {
        router.delete(destroy(link.id).url, {
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
                    <DialogTitle>Delete this short link?</DialogTitle>
                    <DialogDescription>
                        /{link.slug} will stop working immediately. Its click
                        history will also be removed.
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
                        onClick={deleteLink}
                        disabled={processing}
                    >
                        {processing && <Spinner data-icon="inline-start" />}
                        Delete link
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function LinkActions({
    link,
    folders,
    customDomains,
}: {
    link: LinkListItem;
    folders: FolderOption[];
    customDomains: CustomDomainOption[];
}) {
    const [updatingStatus, setUpdatingStatus] = useState(false);

    function toggleStatus() {
        router.patch(
            update(link.id).url,
            {
                destination_url: link.destinationUrl,
                title: link.title ?? '',
                slug: link.slug,
                is_active: !link.isActive,
                folder_id: link.folderId,
                custom_domain_id: link.customDomainId,
                available_from: link.availableFrom,
                expires_at: link.expiresAt,
                access_code: '',
                clear_access_code: false,
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
            <LinkFormDialog
                link={link}
                folders={folders}
                customDomains={customDomains}
            >
                <Button variant="outline" size="sm">
                    <Pencil data-icon="inline-start" />
                    Edit
                </Button>
            </LinkFormDialog>
            <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={toggleStatus}
                disabled={updatingStatus}
            >
                {updatingStatus ? (
                    <Spinner data-icon="inline-start" />
                ) : link.isActive ? (
                    <Pause data-icon="inline-start" />
                ) : (
                    <Play data-icon="inline-start" />
                )}
                {link.isActive ? 'Pause' : 'Activate'}
            </Button>
            <Button variant="outline" size="sm" asChild>
                <Link href={insights(link.id)}>
                    <BarChart3 data-icon="inline-start" />
                    Insights
                </Link>
            </Button>
            <DeleteLinkDialog link={link} />
        </div>
    );
}

function LinkRow({
    link,
    folders,
    customDomains,
}: {
    link: LinkListItem;
    folders: FolderOption[];
    customDomains: CustomDomainOption[];
}) {
    const shortLinkUrl = link.customDomain
        ? `https://${link.customDomain.hostname}/${link.slug}`
        : `/${link.slug}`;
    const shortLinkLabel = link.customDomain
        ? `${link.customDomain.hostname}/${link.slug}`
        : `/${link.slug}`;

    return (
        <Card>
            <CardHeader className="gap-4">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div className="flex min-w-0 flex-col gap-1">
                        <div className="flex flex-wrap items-center gap-2">
                            <CardTitle>
                                {link.title || 'Untitled route'}
                            </CardTitle>
                            <Badge
                                variant={link.isActive ? 'default' : 'outline'}
                            >
                                {link.isActive ? 'Live' : 'Paused'}
                            </Badge>
                        </div>
                        <CardDescription className="truncate">
                            {link.destinationUrl}
                        </CardDescription>
                    </div>
                    <a
                        href={shortLinkUrl}
                        target="_blank"
                        rel="noreferrer"
                        className="flex w-fit items-center gap-1 text-sm font-medium text-primary underline-offset-4 hover:underline"
                    >
                        {shortLinkLabel}
                        <ExternalLink aria-hidden="true" />
                        <span className="sr-only">Open short link</span>
                    </a>
                </div>
            </CardHeader>
            <CardContent className="flex flex-col gap-4">
                <Separator />
                <dl className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div className="flex flex-col gap-1">
                        <dt className="text-sm text-muted-foreground">
                            Destination
                        </dt>
                        <dd className="min-w-0 truncate text-sm font-medium">
                            <a
                                href={link.destinationUrl}
                                target="_blank"
                                rel="noreferrer"
                                className="underline-offset-4 hover:underline"
                            >
                                {link.destinationUrl}
                            </a>
                        </dd>
                    </div>
                    <div className="flex flex-col gap-1">
                        <dt className="text-sm text-muted-foreground">
                            Clicks
                        </dt>
                        <dd className="flex items-center gap-2 text-sm font-medium tabular-nums">
                            <MousePointerClick aria-hidden="true" />
                            {link.clicksCount}{' '}
                            {link.clicksCount === 1 ? 'click' : 'clicks'}
                        </dd>
                    </div>
                    <div className="flex flex-col gap-1">
                        <dt className="text-sm text-muted-foreground">
                            Folder
                        </dt>
                        <dd className="flex items-center gap-2 text-sm font-medium">
                            <Folder aria-hidden="true" />
                            {link.folder?.name ?? 'No folder'}
                        </dd>
                    </div>
                    {(link.availableFrom || link.expiresAt) && (
                        <div className="flex flex-col gap-1">
                            <dt className="text-sm text-muted-foreground">
                                Availability
                            </dt>
                            <dd className="flex items-start gap-2 text-sm font-medium">
                                <CalendarClock
                                    className="mt-0.5 shrink-0"
                                    aria-hidden="true"
                                />
                                <span>
                                    {link.availableFrom && (
                                        <span className="block">
                                            Starts{' '}
                                            {new Date(
                                                link.availableFrom,
                                            ).toLocaleString()}
                                        </span>
                                    )}
                                    {link.expiresAt && (
                                        <span className="block">
                                            Expires{' '}
                                            {new Date(
                                                link.expiresAt,
                                            ).toLocaleString()}
                                        </span>
                                    )}
                                </span>
                            </dd>
                        </div>
                    )}
                    {link.hasAccessCode && (
                        <div className="flex flex-col gap-1">
                            <dt className="text-sm text-muted-foreground">
                                Access
                            </dt>
                            <dd className="flex items-center gap-2 text-sm font-medium">
                                <KeyRound aria-hidden="true" />
                                Access code required
                            </dd>
                        </div>
                    )}
                </dl>
            </CardContent>
            <CardFooter className="flex-wrap gap-2">
                <LinkActions
                    link={link}
                    folders={folders}
                    customDomains={customDomains}
                />
            </CardFooter>
        </Card>
    );
}

export default function ShortLinks({
    links: linkPayloads,
    folders,
    customDomains,
}: LinkIndexProps) {
    const links = linkPayloads.map(toLinkListItem);

    return (
        <>
            <Head title="Short links" />

            <main className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <section className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div className="flex max-w-2xl flex-col gap-2">
                        <div className="flex items-center gap-2 text-linkpilot-creator">
                            <Link2 aria-hidden="true" />
                            <span className="text-sm font-medium">
                                Routing desk
                            </span>
                        </div>
                        <h1 className="text-3xl font-semibold tracking-tight">
                            Short links
                        </h1>
                        <p className="text-sm leading-6 text-muted-foreground">
                            Create compact routes, control when they resolve,
                            and inspect the traffic they receive.
                        </p>
                    </div>
                    <LinkFormDialog
                        folders={folders}
                        customDomains={customDomains}
                    >
                        <Button>
                            <Plus data-icon="inline-start" />
                            Create short link
                        </Button>
                    </LinkFormDialog>
                </section>

                {links.length === 0 ? (
                    <Empty className="bg-muted/30">
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <Activity aria-hidden="true" />
                            </EmptyMedia>
                            <EmptyTitle>No short links yet</EmptyTitle>
                            <EmptyDescription>
                                Add a destination to create a route you can
                                share and measure.
                            </EmptyDescription>
                        </EmptyHeader>
                        <EmptyContent>
                            <LinkFormDialog
                                folders={folders}
                                customDomains={customDomains}
                            >
                                <Button>
                                    <Plus data-icon="inline-start" />
                                    Create your first link
                                </Button>
                            </LinkFormDialog>
                        </EmptyContent>
                    </Empty>
                ) : (
                    <section
                        aria-label="Short links"
                        className="flex flex-col gap-3"
                    >
                        {links.map((link) => (
                            <LinkRow
                                key={link.id}
                                link={link}
                                folders={folders}
                                customDomains={customDomains}
                            />
                        ))}
                    </section>
                )}
            </main>
        </>
    );
}

ShortLinks.layout = {
    breadcrumbs: [
        {
            title: 'Short links',
            href: index(),
        },
    ],
};
