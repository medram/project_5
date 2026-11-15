import { Head, Link, router, useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';
import {
    Bell,
    CheckCheck,
    ChevronLeft,
    ChevronRight,
    Mail,
} from 'lucide-react';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    index as notificationsIndex,
    read,
    readAll,
} from '@/routes/notifications';
import { update as updatePreferences } from '@/routes/notifications/preferences';
import type {
    NotificationCategory,
    NotificationCenterProps,
} from '@/types/notifications';

const categoryLabels: Record<NotificationCategory, string> = {
    invitations: 'Invitations',
    domains: 'Domains',
    webhooks: 'Webhooks',
    usage: 'Usage',
    billing: 'Billing',
    admin: 'Platform',
};

export default function NotificationCenter({
    filter,
    notifications,
    unreadCount,
    pagination,
    emailPreferences,
}: NotificationCenterProps) {
    const preferencesForm = useForm({
        emailPreferences: Object.fromEntries(
            emailPreferences.map(({ category, enabled }) => [
                category,
                enabled,
            ]),
        ) as Record<NotificationCategory, boolean>,
    });

    function markRead(id: string) {
        router.post(read(id).url, {}, { preserveScroll: true });
    }

    function markAllRead() {
        router.post(readAll().url, {}, { preserveScroll: true });
    }

    function savePreferences(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        preferencesForm.patch(updatePreferences().url, {
            preserveScroll: true,
        });
    }

    return (
        <>
            <Head title="Notifications" />
            <section className="mx-auto flex w-full max-w-7xl min-w-0 flex-1 flex-col gap-5 px-5 pt-4 pb-10">
                <PageHeader
                    title="Notifications"
                    description="Review workspace activity and choose which categories may also reach you by email."
                    actions={
                        <Button
                            type="button"
                            variant="outline"
                            disabled={unreadCount === 0}
                            onClick={markAllRead}
                        >
                            <CheckCheck aria-hidden="true" />
                            Mark all read
                        </Button>
                    }
                />

                <div className="grid gap-6 lg:grid-cols-[minmax(0,1.35fr)_minmax(17rem,0.65fr)]">
                    <Card className="min-w-0">
                        <CardHeader className="gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div className="space-y-1.5">
                                <CardTitle>Activity</CardTitle>
                                <CardDescription>
                                    {unreadCount === 0
                                        ? 'You are all caught up.'
                                        : `${unreadCount} unread ${unreadCount === 1 ? 'notification' : 'notifications'}`}
                                </CardDescription>
                            </div>
                            <nav
                                aria-label="Notification filter"
                                className="flex gap-2"
                            >
                                {(['all', 'unread'] as const).map((value) => (
                                    <Button
                                        key={value}
                                        asChild
                                        size="sm"
                                        variant={
                                            filter === value
                                                ? 'default'
                                                : 'outline'
                                        }
                                    >
                                        <Link
                                            href={
                                                notificationsIndex({
                                                    query: { filter: value },
                                                }).url
                                            }
                                            aria-current={
                                                filter === value
                                                    ? 'page'
                                                    : undefined
                                            }
                                        >
                                            {value === 'all' ? 'All' : 'Unread'}
                                        </Link>
                                    </Button>
                                ))}
                            </nav>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {notifications.length === 0 ? (
                                <div className="flex flex-col items-center gap-3 rounded-lg border border-dashed px-5 py-12 text-center">
                                    <span className="flex size-11 items-center justify-center rounded-full bg-muted text-muted-foreground">
                                        <Bell
                                            className="size-5"
                                            aria-hidden="true"
                                        />
                                    </span>
                                    <div className="space-y-1">
                                        <p className="font-medium">
                                            {filter === 'unread'
                                                ? 'No unread notifications'
                                                : 'No notifications yet'}
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            Workspace and platform updates will
                                            appear here.
                                        </p>
                                    </div>
                                </div>
                            ) : (
                                <ul className="space-y-3">
                                    {notifications.map((notification) => (
                                        <li key={notification.id}>
                                            <article
                                                className={`rounded-lg border p-4 ${notification.readAt === null ? 'border-primary/30 bg-primary/[0.03]' : 'bg-card'}`}
                                            >
                                                <div className="flex items-start justify-between gap-3">
                                                    <div className="min-w-0 space-y-2">
                                                        <div className="flex flex-wrap items-center gap-2">
                                                            <h3 className="leading-5 font-medium">
                                                                {
                                                                    notification.title
                                                                }
                                                            </h3>
                                                            <Badge variant="secondary">
                                                                {
                                                                    categoryLabels[
                                                                        notification
                                                                            .category
                                                                    ]
                                                                }
                                                            </Badge>
                                                            {notification.readAt ===
                                                                null && (
                                                                <Badge variant="outline">
                                                                    Unread
                                                                </Badge>
                                                            )}
                                                        </div>
                                                        <p className="text-sm leading-6 text-muted-foreground">
                                                            {notification.body}
                                                        </p>
                                                        <div className="flex flex-wrap items-center gap-x-4 gap-y-2 text-sm">
                                                            <Link
                                                                href={
                                                                    notification.href
                                                                }
                                                                className="font-medium text-primary underline-offset-4 hover:underline"
                                                            >
                                                                {notification.category ===
                                                                'admin'
                                                                    ? 'Open notification center'
                                                                    : 'Review workspace'}
                                                            </Link>
                                                            {notification.createdAt && (
                                                                <time
                                                                    dateTime={
                                                                        notification.createdAt
                                                                    }
                                                                    className="text-muted-foreground"
                                                                >
                                                                    {new Intl.DateTimeFormat(
                                                                        undefined,
                                                                        {
                                                                            dateStyle:
                                                                                'medium',
                                                                            timeStyle:
                                                                                'short',
                                                                        },
                                                                    ).format(
                                                                        new Date(
                                                                            notification.createdAt,
                                                                        ),
                                                                    )}
                                                                </time>
                                                            )}
                                                        </div>
                                                    </div>
                                                    {notification.readAt ===
                                                        null && (
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            variant="ghost"
                                                            className="shrink-0"
                                                            onClick={() =>
                                                                markRead(
                                                                    notification.id,
                                                                )
                                                            }
                                                            aria-label={`Mark ${notification.title} as read`}
                                                        >
                                                            Mark read
                                                        </Button>
                                                    )}
                                                </div>
                                            </article>
                                        </li>
                                    ))}
                                </ul>
                            )}

                            {pagination.lastPage > 1 && (
                                <div className="flex items-center justify-between border-t pt-4">
                                    <p className="text-sm text-muted-foreground">
                                        Page {pagination.currentPage} of{' '}
                                        {pagination.lastPage}
                                    </p>
                                    <div className="flex gap-2">
                                        <Button
                                            asChild
                                            size="sm"
                                            variant="outline"
                                        >
                                            <Link
                                                href={
                                                    notificationsIndex({
                                                        query: {
                                                            filter,
                                                            page: Math.max(
                                                                1,
                                                                pagination.currentPage -
                                                                    1,
                                                            ),
                                                        },
                                                    }).url
                                                }
                                                aria-label="Previous page"
                                                aria-disabled={
                                                    pagination.currentPage <= 1
                                                }
                                                tabIndex={
                                                    pagination.currentPage <= 1
                                                        ? -1
                                                        : 0
                                                }
                                                onClick={(event) => {
                                                    if (
                                                        pagination.currentPage <=
                                                        1
                                                    ) {
                                                        event.preventDefault();
                                                    }
                                                }}
                                            >
                                                <ChevronLeft aria-hidden="true" />
                                                Previous
                                            </Link>
                                        </Button>
                                        <Button
                                            asChild
                                            size="sm"
                                            variant="outline"
                                        >
                                            <Link
                                                href={
                                                    notificationsIndex({
                                                        query: {
                                                            filter,
                                                            page: Math.min(
                                                                pagination.lastPage,
                                                                pagination.currentPage +
                                                                    1,
                                                            ),
                                                        },
                                                    }).url
                                                }
                                                aria-label="Next page"
                                                aria-disabled={
                                                    pagination.currentPage >=
                                                    pagination.lastPage
                                                }
                                                tabIndex={
                                                    pagination.currentPage >=
                                                    pagination.lastPage
                                                        ? -1
                                                        : 0
                                                }
                                                onClick={(event) => {
                                                    if (
                                                        pagination.currentPage >=
                                                        pagination.lastPage
                                                    ) {
                                                        event.preventDefault();
                                                    }
                                                }}
                                            >
                                                Next
                                                <ChevronRight aria-hidden="true" />
                                            </Link>
                                        </Button>
                                    </div>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    <Card className="h-fit">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Mail className="size-4" aria-hidden="true" />
                                Email preferences
                            </CardTitle>
                            <CardDescription>
                                In-app notifications remain enabled. Email
                                requires a configured mail service and an
                                enabled category.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form
                                onSubmit={savePreferences}
                                className="space-y-5"
                            >
                                <fieldset
                                    className="space-y-4"
                                    disabled={preferencesForm.processing}
                                >
                                    <legend className="sr-only">
                                        Email notification categories
                                    </legend>
                                    {emailPreferences.map((preference) => (
                                        <label
                                            key={preference.category}
                                            className="flex cursor-pointer items-start gap-3"
                                        >
                                            <Checkbox
                                                checked={Boolean(
                                                    preferencesForm.data
                                                        .emailPreferences[
                                                        preference.category
                                                    ],
                                                )}
                                                disabled={
                                                    preferencesForm.processing
                                                }
                                                onCheckedChange={(checked) =>
                                                    preferencesForm.setData(
                                                        'emailPreferences',
                                                        {
                                                            ...preferencesForm
                                                                .data
                                                                .emailPreferences,
                                                            [preference.category]:
                                                                checked ===
                                                                true,
                                                        },
                                                    )
                                                }
                                                aria-label={preference.label}
                                            />
                                            <span className="grid gap-1 text-sm">
                                                <span className="font-medium">
                                                    {preference.label}
                                                </span>
                                                <span className="text-muted-foreground">
                                                    {preference.category ===
                                                    'admin'
                                                        ? 'Platform announcements'
                                                        : `Updates about ${preference.label.toLowerCase()}`}
                                                </span>
                                            </span>
                                        </label>
                                    ))}
                                </fieldset>
                                {preferencesForm.errors.emailPreferences && (
                                    <p
                                        role="alert"
                                        className="text-sm text-destructive"
                                    >
                                        {
                                            preferencesForm.errors
                                                .emailPreferences
                                        }
                                    </p>
                                )}
                                <Button
                                    type="submit"
                                    disabled={preferencesForm.processing}
                                >
                                    {preferencesForm.processing
                                        ? 'Saving…'
                                        : 'Save preferences'}
                                </Button>
                            </form>
                        </CardContent>
                    </Card>
                </div>
            </section>
        </>
    );
}
