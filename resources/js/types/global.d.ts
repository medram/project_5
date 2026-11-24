import type { Auth, PlatformBranding } from '@/types/auth';
import type { NotificationPreview } from '@/types/notifications';
import type { WorkspaceContext } from '@/types/workspaces';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare global {
    interface Window {
        adsbygoogle?: unknown[];
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            platform?: PlatformBranding;
            locale: { code: string; direction: string };
            translations: Record<string, string>;
            auth: Auth;
            notificationUnreadCount: number;
            notificationPreview: NotificationPreview[];
            sidebarOpen: boolean;
            errors: Record<string, string>;
            workspace: WorkspaceContext | null;
            googleAuthEnabled: boolean;
            [key: string]: unknown;
        };
    }
}
