import { Link } from '@inertiajs/react';
import { Link2 } from 'lucide-react';
import AdminSidebar from '@/components/admin/admin-sidebar';
import { AppContent } from '@/components/app-content';
import AppLogo from '@/components/app-logo';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { AppShell } from '@/components/app-shell';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { index as adminIndex } from '@/routes/admin';
import { index as linksIndex } from '@/routes/links';
import type { BreadcrumbItem } from '@/types';

export default function AdminLayout({
    breadcrumbs = [],
    children,
}: {
    breadcrumbs?: BreadcrumbItem[];
    children: React.ReactNode;
}) {
    return (
        <AppShell variant="sidebar">
            <Sidebar collapsible="icon" variant="inset">
                <SidebarHeader>
                    <SidebarMenu>
                        <SidebarMenuItem>
                            <SidebarMenuButton size="lg" asChild>
                                <Link href={adminIndex()} prefetch>
                                    <AppLogo />
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarHeader>
                <AdminSidebar />
                <SidebarFooter className="gap-3">
                    <SidebarMenu>
                        <SidebarMenuItem>
                            <SidebarMenuButton asChild tooltip="Workspace app">
                                <Link href={linksIndex()} prefetch>
                                    <Link2 />
                                    <span>Workspace app</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                    <NavUser />
                </SidebarFooter>
            </Sidebar>
            <AppContent variant="sidebar" className="min-w-0 overflow-x-clip">
                <AppSidebarHeader
                    breadcrumbs={breadcrumbs}
                    showWorkspace={false}
                    showSearch
                />
                {children}
            </AppContent>
        </AppShell>
    );
}
