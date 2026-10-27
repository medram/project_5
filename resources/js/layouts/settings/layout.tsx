import { Link } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn, toUrl } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { edit } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import { index as billingIndex } from '@/routes/billing';
import { CreditCard, KeyRound, Users } from 'lucide-react';
import { index as webhooksIndex } from '@/routes/webhooks';
import { index as apiKeysIndex } from '@/routes/api-keys';
import type { NavItem } from '@/types';

const sidebarNavItems: NavItem[] = [
    {
        title: 'Profile',
        href: edit(),
        icon: null,
    },
    {
        title: 'Security',
        href: editSecurity(),
        icon: null,
    },
    {
        title: 'Appearance',
        href: editAppearance(),
        icon: null,
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
        icon: null,
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
        isCurrentOrParentUrl(apiKeysIndex());

    return (
        <div className="px-4 py-6">
            <Heading
                title="Settings"
                description="Manage your profile and account settings"
            />

            <div className="flex flex-col lg:flex-row lg:space-x-12">
                <aside className="w-full max-w-xl lg:w-48">
                    <nav
                        className="flex flex-col space-y-1 space-x-0"
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
                                    className={cn('w-full justify-start', {
                                        'bg-muted': active,
                                    })}
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

                <Separator className="my-6 lg:hidden" />

                <div
                    className={cn(
                        'flex-1 md:max-w-2xl',
                        isWideSettingsRoute && 'md:max-w-none',
                    )}
                >
                    <section
                        className={cn(
                            'space-y-12',
                            isWideSettingsRoute ? 'max-w-none' : 'max-w-xl',
                        )}
                    >
                        {children}
                    </section>
                </div>
            </div>
        </div>
    );
}
