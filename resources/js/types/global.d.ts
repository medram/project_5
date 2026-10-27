import type { Auth } from '@/types/auth';
import type { NotificationPreview } from '@/types/notifications';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            notificationUnreadCount: number;
            notificationPreview: NotificationPreview[];
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}
