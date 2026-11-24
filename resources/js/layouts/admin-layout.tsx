import { Link } from '@inertiajs/react';
import { Link2 } from 'lucide-react';
import AdminSidebar from '@/components/admin/admin-sidebar';
import AppLogo from '@/components/app-logo';
import { AppContent } from '@/components/app-content';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { AppShell } from '@/components/app-shell';
import { SidebarIdentity } from '@/components/sidebar-identity';
import {
    Sidebar,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
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
            <Sidebar collapsible="icon" variant="sidebar">
                <SidebarHeader className="gap-1.5 p-3 pb-1 group-data-[collapsible=icon]:px-2">
                    <Link
                        href="/admin/insights"
                        prefetch
                        className="flex min-h-10 min-w-0 items-center rounded-md group-data-[collapsible=icon]:justify-center focus-visible:ring-2 focus-visible:ring-ring/40 focus-visible:outline-none"
                    >
                        <AppLogo hideNameWhenCollapsed />
                    </Link>
                </SidebarHeader>
                <AdminSidebar />
                <SidebarFooter className="gap-1 p-3 pt-1 group-data-[collapsible=icon]:px-2 max-lg:border-t max-lg:border-sidebar-border">
                    <SidebarMenu>
                        <SidebarMenuItem>
                            <SidebarMenuButton asChild tooltip="Workspace app">
                                <Link href={linksIndex()} prefetch>
                                    <Link2 aria-hidden="true" />
                                    <span>Workspace app</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                    <SidebarIdentity roleLabel="Platform administrator" />
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
