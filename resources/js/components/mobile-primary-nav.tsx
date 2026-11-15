import { Link, usePage } from '@inertiajs/react';
import { UserRound } from 'lucide-react';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn } from '@/lib/utils';
import { edit as profileEdit } from '@/routes/profile';
import { filteredWorkspaceNavSections } from '@/components/workspace-navigation';
import type { NavItem } from '@/types';

export function MobilePrimaryNav() {
    const { isCurrentOrParentUrl, isCurrentUrl } = useCurrentUrl();
    const platform = usePage().props.platform;
    const availableItems = filteredWorkspaceNavSections(
        platform?.capabilities,
    ).flatMap((section) => section.items);
    const primaryItems: NavItem[] = [
        {
            ...availableItems.find((item) => item.title === 'Short links')!,
            title: 'Links',
        },
        {
            ...availableItems.find((item) => item.title === 'Link in bio')!,
            title: 'Bio',
        },
        {
            ...availableItems.find((item) => item.title === 'QR codes')!,
            title: 'QR',
        },
        {
            ...availableItems.find((item) => item.title === 'Insights')!,
            title: 'Insights',
        },
        { title: 'Account', href: profileEdit(), icon: UserRound },
    ];

    return (
        <div className="fixed inset-x-0 bottom-0 z-20 border-t border-border bg-card pb-[env(safe-area-inset-bottom)] min-[1025px]:hidden">
            <nav
                aria-label="Primary navigation"
                className="mx-auto flex w-full max-w-screen-md items-stretch gap-1 px-2 pt-1"
            >
                {primaryItems.map((item) => {
                    const Icon = item.icon;
                    const active = isCurrentOrParentUrl(
                        item.title === 'Account'
                            ? '/account-settings'
                            : item.href,
                    );
                    const current = active && isCurrentUrl(item.href);

                    return (
                        <Link
                            key={item.title}
                            href={item.href}
                            aria-current={
                                active
                                    ? current
                                        ? 'page'
                                        : 'location'
                                    : undefined
                            }
                            className="flex h-14 min-w-0 flex-1 flex-col items-center justify-center gap-1 rounded-lg text-[11px] font-medium text-muted-foreground transition-colors"
                        >
                            {Icon && (
                                <span
                                    className={cn(
                                        'flex h-6 min-w-9 items-center justify-center rounded-md px-2',
                                        active && 'bg-default text-foreground',
                                    )}
                                >
                                    <Icon
                                        aria-hidden="true"
                                        className="size-[18px]"
                                    />
                                </span>
                            )}
                            <span className={cn(active && 'text-foreground')}>
                                {item.title}
                            </span>
                        </Link>
                    );
                })}
            </nav>
        </div>
    );
}
