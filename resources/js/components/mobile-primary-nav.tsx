import { Link } from '@inertiajs/react';
import { UserRound } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { edit as profileEdit } from '@/routes/profile';
import { workspaceNavSections } from '@/components/workspace-navigation';
import type { NavItem } from '@/types';

export function MobilePrimaryNav() {
    const { isCurrentOrParentUrl, isCurrentUrl } = useCurrentUrl();
    const availableItems = workspaceNavSections.flatMap(
        (section) => section.items,
    );
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
        <div className="fixed inset-x-0 bottom-0 z-20 border-t bg-background pb-[env(safe-area-inset-bottom)] min-[1025px]:hidden">
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
                        <Button
                            key={item.title}
                            variant={active ? 'secondary' : 'ghost'}
                            size="sm"
                            className="h-14 min-w-0 flex-1 flex-col gap-1 px-1 py-1 text-[10px]"
                            asChild
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
                                {Icon && (
                                    <Icon
                                        data-icon="inline-start"
                                        aria-hidden="true"
                                    />
                                )}
                                <span className="max-w-full truncate">
                                    {item.title}
                                </span>
                            </Link>
                        </Button>
                    );
                })}
            </nav>
        </div>
    );
}
