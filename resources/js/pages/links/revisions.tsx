import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { ArrowLeft, Check, History, X } from 'lucide-react';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Textarea } from '@/components/ui/textarea';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import UserLayout from '@/layouts/user-layout';
import { index } from '@/routes/links';
import type { LinkRevisionPageProps } from '@/types/links';

function displayDate(value: string | null): string {
    return value === null ? '—' : new Date(value).toLocaleString();
}

function withListQuery(url: string, listQuery: string): string {
    return listQuery === '' ? url : `${url}?${listQuery}`;
}

export default function LinkRevisions({
    link,
    revisions,
    canReview,
    listQuery,
}: LinkRevisionPageProps) {
    function review(path: string): void {
        router.post(
            withListQuery(path, listQuery),
            {},
            { preserveScroll: true },
        );
    }
    const [rejectingRevisionId, setRejectingRevisionId] = useState<
        number | null
    >(null);
    const [reviewNote, setReviewNote] = useState('');
    const [submittingRejection, setSubmittingRejection] = useState(false);

    function closeRejectionDialog(open: boolean): void {
        if (!open && !submittingRejection) {
            setRejectingRevisionId(null);
            setReviewNote('');
        }
    }

    function submitRejection(): void {
        if (rejectingRevisionId === null) {
            return;
        }

        router.post(
            withListQuery(
                `/dashboard/links/${link.id}/revisions/${rejectingRevisionId}/reject`,
                listQuery,
            ),
            { note: reviewNote.trim() || null },
            {
                preserveScroll: true,
                onStart: () => setSubmittingRejection(true),
                onSuccess: () => {
                    setRejectingRevisionId(null);
                    setReviewNote('');
                },
                onFinish: () => setSubmittingRejection(false),
            },
        );
    }

    return (
        <>
            <Head title={`History for /${link.slug}`} />
            <section className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <section className="flex flex-col gap-4">
                    <Button
                        variant="outline"
                        size="sm"
                        className="w-fit"
                        asChild
                    >
                        <Link href={withListQuery(index().url, listQuery)}>
                            <ArrowLeft data-icon="inline-start" />
                            Short links
                        </Link>
                    </Button>
                    <PageHeader
                        title={`/${link.slug}`}
                        eyebrow="Revision history"
                        icon={History}
                        description="Immutable delivery snapshots. Pending changes do not affect the live route until an authorized review applies them."
                    />
                </section>

                {revisions.length === 0 ? (
                    <Card>
                        <CardHeader>
                            <CardTitle>No revisions recorded</CardTitle>
                            <CardDescription>
                                Changes to this link will appear here.
                            </CardDescription>
                        </CardHeader>
                    </Card>
                ) : (
                    <section
                        aria-label="Link revisions"
                        className="flex flex-col gap-4"
                    >
                        {revisions.map((revision) => (
                            <Card key={revision.id}>
                                <CardHeader className="gap-3 sm:flex-row sm:items-start sm:justify-between">
                                    <div className="min-w-0">
                                        <CardTitle className="[overflow-wrap:anywhere] break-words">
                                            Revision {revision.number}
                                            {revision.title
                                                ? ` · ${revision.title}`
                                                : ''}
                                        </CardTitle>
                                        <CardDescription>
                                            Created by {revision.actor} on{' '}
                                            {displayDate(revision.createdAt)}
                                        </CardDescription>
                                    </div>
                                    <Badge
                                        variant={
                                            revision.status === 'pending'
                                                ? 'secondary'
                                                : revision.status ===
                                                        'rejected' ||
                                                    revision.status === 'stale'
                                                  ? 'destructive'
                                                  : 'default'
                                        }
                                    >
                                        {revision.status}
                                    </Badge>
                                </CardHeader>
                                <CardContent className="grid gap-4 sm:grid-cols-2">
                                    <div className="min-w-0">
                                        <p className="text-sm font-medium">
                                            Delivery
                                        </p>
                                        <p className="text-sm text-muted-foreground capitalize">
                                            {revision.deliveryMode.replaceAll(
                                                '_',
                                                ' ',
                                            )}
                                        </p>
                                        <p className="mt-2 font-mono text-xs break-all text-muted-foreground">
                                            {revision.target ||
                                                'No destination'}
                                        </p>
                                        {revision.routingRules.length > 0 && (
                                            <div className="mt-3 space-y-1">
                                                <p className="text-sm font-medium">
                                                    Ordered routing rules
                                                </p>
                                                {revision.routingRules.map(
                                                    (rule) => (
                                                        <div
                                                            key={rule.position}
                                                            className="min-w-0 text-xs text-muted-foreground"
                                                        >
                                                            <span>
                                                                {rule.position}.{' '}
                                                                {rule.condition}
                                                            </span>
                                                            <p className="font-mono break-all">
                                                                {
                                                                    rule.destination
                                                                }
                                                            </p>
                                                        </div>
                                                    ),
                                                )}
                                            </div>
                                        )}
                                    </div>
                                    <div className="min-w-0 space-y-1 text-sm text-muted-foreground">
                                        <p>
                                            Slug:{' '}
                                            <span className="font-mono break-all">
                                                {revision.slug ?? '—'}
                                            </span>
                                        </p>
                                        <p>
                                            Active:{' '}
                                            {revision.isActive ? 'Yes' : 'No'}
                                        </p>
                                        <p>
                                            Folder:{' '}
                                            {revision.folderLabel ?? 'None'}
                                        </p>
                                        <p>
                                            Custom domain:{' '}
                                            {revision.customDomainLabel ??
                                                'Default domain'}
                                        </p>
                                        <p>
                                            Link page:{' '}
                                            {revision.pageLabel ?? 'None'}
                                        </p>
                                        <p>
                                            Overlay:{' '}
                                            {revision.overlayLabel ?? 'None'}
                                        </p>
                                        <p>
                                            Tags:{' '}
                                            {revision.tagLabels.length > 0
                                                ? revision.tagLabels.join(', ')
                                                : 'None'}
                                        </p>
                                        <p>
                                            Available from:{' '}
                                            {displayDate(
                                                revision.availableFrom,
                                            )}
                                        </p>
                                        <p>
                                            Expires:{' '}
                                            {displayDate(revision.expiresAt)}
                                        </p>
                                        <p>
                                            Maximum clicks:{' '}
                                            {revision.maxClicks ?? 'Unlimited'}
                                        </p>
                                        <p>
                                            Approval required:{' '}
                                            {revision.approvalRequired
                                                ? 'Yes'
                                                : 'No'}
                                        </p>
                                        {revision.approvalStatus && (
                                            <p>
                                                Approval status:{' '}
                                                {revision.approvalStatus}
                                            </p>
                                        )}
                                        <p>
                                            Access code:{' '}
                                            {revision.hasAccessCode
                                                ? 'Enabled'
                                                : 'Not enabled'}
                                        </p>
                                        <p>
                                            UTM parameters:{' '}
                                            <span className="font-mono text-xs break-all">
                                                {JSON.stringify(
                                                    revision.utmParameters,
                                                )}
                                            </span>
                                        </p>
                                        <p>
                                            Custom parameters:{' '}
                                            <span className="font-mono text-xs break-all">
                                                {JSON.stringify(
                                                    revision.customParameters,
                                                )}
                                            </span>
                                        </p>
                                        {revision.reviewer && (
                                            <p>
                                                Reviewed by {revision.reviewer}{' '}
                                                on{' '}
                                                {displayDate(
                                                    revision.reviewedAt,
                                                )}
                                            </p>
                                        )}
                                        {revision.reviewNote && (
                                            <p className="mt-2 [overflow-wrap:anywhere]">
                                                Review note:{' '}
                                                {revision.reviewNote}
                                            </p>
                                        )}
                                    </div>
                                </CardContent>
                                {canReview && revision.status === 'pending' && (
                                    <CardFooter className="flex-wrap gap-2">
                                        <Button
                                            type="button"
                                            onClick={() =>
                                                review(
                                                    `/dashboard/links/${link.id}/revisions/${revision.id}/approve`,
                                                )
                                            }
                                        >
                                            <Check data-icon="inline-start" />
                                            Approve and publish
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() => {
                                                setReviewNote('');
                                                setRejectingRevisionId(
                                                    revision.id,
                                                );
                                            }}
                                        >
                                            <X data-icon="inline-start" />
                                            Reject
                                        </Button>
                                    </CardFooter>
                                )}
                            </Card>
                        ))}
                    </section>
                )}
                <Dialog
                    open={rejectingRevisionId !== null}
                    onOpenChange={closeRejectionDialog}
                >
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Reject revision</DialogTitle>
                            <DialogDescription>
                                Add an optional note to explain why this
                                proposed change was rejected.
                            </DialogDescription>
                        </DialogHeader>
                        <div className="grid gap-2">
                            <label
                                htmlFor="revision-review-note"
                                className="text-sm font-medium"
                            >
                                Review note (optional)
                            </label>
                            <Textarea
                                id="revision-review-note"
                                value={reviewNote}
                                onChange={(event) =>
                                    setReviewNote(event.target.value)
                                }
                                maxLength={500}
                                disabled={submittingRejection}
                            />
                            <p className="text-xs text-muted-foreground">
                                {reviewNote.length}/500 characters
                            </p>
                        </div>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                disabled={submittingRejection}
                                onClick={() => closeRejectionDialog(false)}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="button"
                                variant="destructive"
                                disabled={submittingRejection}
                                onClick={submitRejection}
                            >
                                Reject revision
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </section>
        </>
    );
}

function LinkRevisionLayout({ children }: { children: ReactNode }) {
    const { props } = usePage<LinkRevisionPageProps>();

    return (
        <UserLayout
            breadcrumbs={[
                {
                    title: 'Short links',
                    href: withListQuery(index().url, props.listQuery),
                },
                {
                    title: 'Revision history',
                    href: withListQuery(
                        `/dashboard/links/${props.link.id}/revisions`,
                        props.listQuery,
                    ),
                },
            ]}
        >
            {children}
        </UserLayout>
    );
}

LinkRevisions.layout = LinkRevisionLayout;
