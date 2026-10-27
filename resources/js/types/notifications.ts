export type NotificationCategory =
    | 'invitations'
    | 'domains'
    | 'webhooks'
    | 'usage'
    | 'billing'
    | 'admin';

export type AccountNotificationItem = {
    id: string;
    category: NotificationCategory;
    title: string;
    body: string;
    createdAt: string | null;
    readAt: string | null;
    href: string;
};
export type NotificationPreview = AccountNotificationItem;

export type NotificationEmailPreference = {
    category: NotificationCategory;
    label: string;
    enabled: boolean;
};

export type NotificationCenterProps = {
    filter: 'all' | 'unread';
    notifications: AccountNotificationItem[];
    unreadCount: number;
    pagination: {
        currentPage: number;
        lastPage: number;
    };
    emailPreferences: NotificationEmailPreference[];
};
