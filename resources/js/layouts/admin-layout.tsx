import { Link } from '@inertiajs/react';
import { HelpCircle, Link2, LogOut } from 'lucide-react';
import AdminSidebar from '@/components/admin/admin-sidebar';
import { AppContent } from '@/components/app-content';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { AppShell } from '@/components/app-shell';
import { HelpDialog } from '@/components/help-dialog';
import { SidebarIdentity } from '@/components/sidebar-identity';
import {
    Sidebar,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { logout } from '@/routes';
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
                <SidebarHeader className="p-3 pb-1">
                    <SidebarIdentity roleLabel="Platform administrator" />
                </SidebarHeader>
                <AdminSidebar />
                <SidebarFooter className="gap-1 p-3 pt-1 max-lg:border-t max-lg:border-sidebar-border">
                    <SidebarMenu>
                        <SidebarMenuItem>
                            <SidebarMenuButton asChild tooltip="Workspace app">
                                <Link href={linksIndex()} prefetch>
                                    <Link2 aria-hidden="true" />
                                    <span>Workspace app</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                        <SidebarMenuItem>
                            <HelpDialog>
                                <SidebarMenuButton>
                                    <HelpCircle aria-hidden="true" />
                                    <span>Help &amp; information</span>
                                </SidebarMenuButton>
                            </HelpDialog>
                        </SidebarMenuItem>
                        <SidebarMenuItem>
                            <SidebarMenuButton asChild>
                                <Link
                                    href={logout()}
                                    method="post"
                                    as="button"
                                    className="w-full"
                                >
                                    <LogOut aria-hidden="true" />
                                    <span>Log out</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
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
