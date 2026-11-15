import { Link } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn, toUrl } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { edit } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import { index as billingIndex } from '@/routes/billing';
import {
    CreditCard,
    KeyRound,
    Palette,
    ShieldCheck,
    UserRound,
    Users,
    Webhook,
} from 'lucide-react';
import { index as webhooksIndex } from '@/routes/webhooks';
import { index as apiKeysIndex } from '@/routes/api-keys';
import type { NavItem } from '@/types';

const sidebarNavItems: NavItem[] = [
    {
        title: 'Profile',
        href: edit(),
        icon: UserRound,
    },
    {
        title: 'Security',
        href: editSecurity(),
        icon: ShieldCheck,
    },
    {
        title: 'Appearance',
        href: editAppearance(),
        icon: Palette,
    },
    {
        title: 'Billing',
        href: billingIndex(),
        icon: CreditCard,
    },
    {
        title: 'Workspaces',
        href: '/account-settings/workspaces',
        icon: Users,
    },
    {
        title: 'Webhooks',
        href: webhooksIndex(),
        icon: Webhook,
    },
    {
        title: 'API keys',
        href: apiKeysIndex(),
        icon: KeyRound,
    },
];

export default function SettingsLayout({ children }: PropsWithChildren) {
    const { isCurrentOrParentUrl, isCurrentUrl } = useCurrentUrl();
    const isWideSettingsRoute =
        isCurrentOrParentUrl(billingIndex()) ||
        isCurrentOrParentUrl(apiKeysIndex()) ||
        isCurrentOrParentUrl(webhooksIndex()) ||
        isCurrentOrParentUrl('/account-settings/workspaces');

    return (
        <div className="mx-auto flex w-full max-w-7xl min-w-0 flex-1 flex-col gap-6 px-5 pt-4 pb-10 lg:flex-row lg:gap-10">
            <aside className="w-full min-w-0 lg:w-52 lg:shrink-0">
                <nav
                    className="flex gap-1 overflow-x-auto lg:flex-col"
                    aria-label="Settings"
                >
                    {sidebarNavItems.map((item, index) => {
                        const active = isCurrentOrParentUrl(item.href);
                        const current = active && isCurrentUrl(item.href);

                        return (
                            <Button
                                key={`${toUrl(item.href)}-${index}`}
                                size="sm"
                                variant="ghost"
                                asChild
                                className={cn(
                                    'h-10 shrink-0 justify-start rounded-lg px-2.5 text-muted-foreground lg:w-full',
                                    active &&
                                        'bg-sidebar-accent font-medium text-foreground hover:bg-sidebar-accent',
                                )}
                            >
                                <Link
                                    href={item.href}
                                    aria-current={
                                        active
                                            ? current
                                                ? 'page'
                                                : 'location'
                                            : undefined
                                    }
                                >
                                    {item.icon && (
                                        <item.icon className="h-4 w-4" />
                                    )}
                                    {item.title}
                                </Link>
                            </Button>
                        );
                    })}
                </nav>
            </aside>

            <Separator className="lg:hidden" />

            <div
                className={cn(
                    'min-w-0 flex-1',
                    !isWideSettingsRoute && 'max-w-2xl',
                )}
            >
                <section
                    className={cn(
                        'space-y-8',
                        isWideSettingsRoute ? 'max-w-none' : 'max-w-xl',
                    )}
                >
                    {children}
                </section>
            </div>
        </div>
    );
}
