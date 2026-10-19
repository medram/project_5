import { Link } from '@inertiajs/react';
import { Folder, Link2, PanelsTopLeft } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { index as linksIndex } from '@/routes/links';
import { index as bioPagesIndex } from '@/routes/bio-pages';
import { index as foldersIndex } from '@/routes/folders';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Short links',
        href: linksIndex(),
        icon: Link2,
    },
    {
        title: 'Folders',
        href: foldersIndex(),
        icon: Folder,
    },
    {
        title: 'Bio pages',
        href: bioPagesIndex(),
        icon: PanelsTopLeft,
    },
];

export function AppSidebar() {
    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={linksIndex()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
