import { Link, usePage } from '@inertiajs/react';
import {
    BarChart3,
    CreditCard,
    Folder,
    Globe2,
    Link2,
    PanelsTopLeft,
    QrCode,
    ShieldCheck,
} from 'lucide-react';
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
import { index as analyticsIndex } from '@/routes/analytics';
import { index as billingIndex } from '@/routes/billing';
import { index as customDomainsIndex } from '@/routes/custom-domains';
import { index as adminIndex } from '@/routes/admin';
import { index as bioPagesIndex } from '@/routes/bio-pages';
import { index as foldersIndex } from '@/routes/folders';
import { index as qrCodesIndex } from '@/routes/qr-codes';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Short links',
        href: linksIndex(),
        icon: Link2,
    },
    {
        title: 'Analytics',
        href: analyticsIndex(),
        icon: BarChart3,
    },
    {
        title: 'Billing & usage',
        href: billingIndex(),
        icon: CreditCard,
    },
    {
        title: 'Custom domains',
        href: customDomainsIndex(),
        icon: Globe2,
    },
    {
        title: 'QR codes',
        href: qrCodesIndex(),
        icon: QrCode,
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
    const { auth } = usePage().props;
    const navigationItems = auth.isAdmin
        ? [
              ...mainNavItems,
              {
                  title: 'Administration',
                  href: adminIndex(),
                  icon: ShieldCheck,
              },
          ]
        : mainNavItems;

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
                <NavMain items={navigationItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
