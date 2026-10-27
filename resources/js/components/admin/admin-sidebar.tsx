import { usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { CreditCard, LayoutDashboard, Link2, Users } from 'lucide-react';
import {
    SidebarContent,
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { index as adminIndex } from '@/routes/admin';

const groups = [
    {
        label: 'Overview',
        items: [
            {
                label: 'Account controls',
                href: adminIndex().url,
                icon: LayoutDashboard,
            },
        ],
    },
    {
        label: 'Monetization',
        items: [
            {
                label: 'Plan assignments',
                href: `${adminIndex().url}#plan-assignments`,
                icon: CreditCard,
            },
        ],
    },
    {
        label: 'Users & access',
        items: [
            {
                label: 'Accounts',
                href: `${adminIndex().url}#accounts`,
                icon: Users,
            },
        ],
    },
    {
        label: 'Content',
        items: [
            {
                label: 'Short-link totals',
                href: `${adminIndex().url}#content`,
                icon: Link2,
            },
        ],
    },
];

export default function AdminSidebar() {
    const { url } = usePage();
    const { isMobile, setOpenMobile } = useSidebar();
    const [hash, setHash] = useState('');

    useEffect(() => {
        const updateHash = () => setHash(window.location.hash.slice(1));
        updateHash();
        window.addEventListener('hashchange', updateHash);

        return () => window.removeEventListener('hashchange', updateHash);
    }, []);

    return (
        <SidebarContent>
            {groups
                .filter((group) => group.items.length > 0)
                .map((group) => (
                    <SidebarGroup key={group.label} className="px-2 py-0">
                        <SidebarGroupLabel>{group.label}</SidebarGroupLabel>
                        <SidebarMenu>
                            {group.items.map((item) => {
                                const Icon = item.icon;
                                const active = hash
                                    ? item.href.split('#')[1] === hash
                                    : item.href === adminIndex().url &&
                                      url.split('?')[0] === adminIndex().url;
                                const current = item.href.includes('#')
                                    ? 'location'
                                    : 'page';

                                return (
                                    <SidebarMenuItem key={item.label}>
                                        <SidebarMenuButton
                                            asChild
                                            isActive={active}
                                            tooltip={item.label}
                                        >
                                            <a
                                                href={item.href}
                                                aria-current={
                                                    active ? current : undefined
                                                }
                                                onClick={() => {
                                                    if (isMobile) {
                                                        setOpenMobile(false);
                                                    }
                                                }}
                                            >
                                                <Icon aria-hidden="true" />
                                                <span>{item.label}</span>
                                            </a>
                                        </SidebarMenuButton>
                                    </SidebarMenuItem>
                                );
                            })}
                        </SidebarMenu>
                    </SidebarGroup>
                ))}
        </SidebarContent>
    );
}
