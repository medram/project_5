import { Link, usePage } from '@inertiajs/react';
import { Bell } from 'lucide-react';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { GlobalSearch } from '@/components/global-search';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';
import { index as notificationsIndex } from '@/routes/notifications';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

export function AppSidebarHeader({
    breadcrumbs = [],
    showWorkspace = true,
    showSearch = true,
}: {
    breadcrumbs?: BreadcrumbItemType[];
    showWorkspace?: boolean;
    showSearch?: boolean;
}) {
    const { workspace, notificationUnreadCount, notificationPreview } =
        usePage().props;

    return (
        <header className="sticky top-0 z-30 flex h-16 shrink-0 items-center gap-2 bg-background px-4 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-16 sm:px-5">
            <div className="flex min-w-0 flex-1 items-center gap-2.5">
                <SidebarTrigger className="-ml-1 size-8 shrink-0 rounded-full text-muted-foreground hover:bg-surface-secondary hover:text-foreground" />
                {breadcrumbs.length > 1 ? (
                    <Breadcrumbs breadcrumbs={breadcrumbs} />
                ) : (
                    <span className="truncate text-[13px] font-medium text-foreground">
                        {showWorkspace && workspace?.current
                            ? workspace.current.name
                            : 'Platform administration'}
                    </span>
                )}
            </div>
            <div className="ml-auto flex min-w-0 items-center gap-2">
                {showSearch && <GlobalSearch />}
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button
                            variant="secondary"
                            size="icon"
                            className="relative size-9 shrink-0 rounded-full"
                            aria-label={
                                notificationUnreadCount > 0
                                    ? `Notifications, ${notificationUnreadCount} unread`
                                    : 'Notifications'
                            }
                            title="Notifications"
                        >
                            <Bell aria-hidden="true" />
                            {notificationUnreadCount > 0 && (
                                <span
                                    aria-hidden="true"
                                    className="absolute top-1.5 right-1.5 size-1.5 rounded-full bg-primary"
                                />
                            )}
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent
                        align="end"
                        className="w-[calc(100vw-2rem)] max-w-sm p-0"
                    >
                        <div className="flex items-start justify-between gap-3 px-3 py-3">
                            <div className="space-y-1">
                                <DropdownMenuLabel className="p-0 text-sm">
                                    Notifications
                                </DropdownMenuLabel>
                                <p className="text-xs text-muted-foreground">
                                    {notificationUnreadCount === 0
                                        ? 'You are all caught up.'
                                        : `${notificationUnreadCount} unread`}
                                </p>
                            </div>
                            {notificationUnreadCount > 0 && (
                                <span className="text-xs text-muted-foreground tabular-nums">
                                    {notificationUnreadCount > 99
                                        ? '99+'
                                        : notificationUnreadCount}
                                </span>
                            )}
                        </div>
                        <DropdownMenuSeparator />
                        {notificationPreview.length === 0 ? (
                            <p className="px-3 py-6 text-center text-sm text-muted-foreground">
                                No notifications yet.
                            </p>
                        ) : (
                            <div className="max-h-80 overflow-y-auto py-1">
                                {notificationPreview.map((notification) => (
                                    <DropdownMenuItem
                                        key={notification.id}
                                        asChild
                                        className="items-start px-3 py-2.5 whitespace-normal"
                                    >
                                        <Link
                                            href={notification.href}
                                            className="flex w-full flex-col items-start gap-1.5"
                                        >
                                            <span className="flex w-full items-center gap-2">
                                                <span
                                                    aria-hidden="true"
                                                    className={`size-2 shrink-0 rounded-full ${notification.readAt === null ? 'bg-primary' : 'bg-transparent'}`}
                                                />
                                                <span className="min-w-0 flex-1 truncate font-medium">
                                                    {notification.title}
                                                </span>
                                                <Badge variant="outline">
                                                    {notification.category}
                                                </Badge>
                                            </span>
                                            <span className="line-clamp-2 text-xs whitespace-normal text-muted-foreground">
                                                {notification.body}
                                            </span>
                                            {notification.createdAt && (
                                                <time
                                                    dateTime={
                                                        notification.createdAt
                                                    }
                                                    className="text-xs text-muted-foreground"
                                                >
                                                    {new Intl.DateTimeFormat(
                                                        undefined,
                                                        {
                                                            dateStyle: 'medium',
                                                            timeStyle: 'short',
                                                        },
                                                    ).format(
                                                        new Date(
                                                            notification.createdAt,
                                                        ),
                                                    )}
                                                </time>
                                            )}
                                        </Link>
                                    </DropdownMenuItem>
                                ))}
                            </div>
                        )}
                        <DropdownMenuSeparator />
                        <div className="grid grid-cols-2 gap-1 p-1">
                            <DropdownMenuItem
                                asChild
                                className="justify-center"
                            >
                                <Link
                                    href={
                                        notificationsIndex({
                                            query: { filter: 'all' },
                                        }).url
                                    }
                                >
                                    All notifications
                                </Link>
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                asChild
                                className="justify-center"
                            >
                                <Link
                                    href={
                                        notificationsIndex({
                                            query: { filter: 'unread' },
                                        }).url
                                    }
                                >
                                    Unread only
                                </Link>
                            </DropdownMenuItem>
                        </div>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </header>
    );
}
