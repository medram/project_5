import { Link, usePage } from '@inertiajs/react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { SidebarIdentity } from '@/components/sidebar-identity';
import { SidebarPlanUsage } from '@/components/sidebar-plan-usage';
import { WorkspaceSwitcher } from '@/components/workspaces/workspace-switcher';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { filteredWorkspaceNavSections } from '@/components/workspace-navigation';
import { dashboard } from '@/routes';

export function AppSidebar() {
    const { isMobile, setOpenMobile } = useSidebar();
    const { workspace, platform } = usePage().props;

    return (
        <Sidebar collapsible="icon" variant="sidebar">
            <SidebarHeader className="gap-1.5 p-3 pb-1 group-data-[collapsible=icon]:px-2">
                <Link
                    href={dashboard()}
                    prefetch
                    className="flex min-h-10 min-w-0 items-center rounded-md focus-visible:ring-2 focus-visible:ring-ring/40 focus-visible:outline-none group-data-[collapsible=icon]:justify-center"
                >
                    <AppLogo hideNameWhenCollapsed />
                </Link>
                <WorkspaceSwitcher />
            </SidebarHeader>

            <SidebarContent className="px-2 pb-2">
                <NavMain
                    sections={filteredWorkspaceNavSections(
                        platform?.capabilities,
                    )}
                />
            </SidebarContent>

            <SidebarFooter className="gap-1 p-3 pt-1 max-lg:border-t max-lg:border-sidebar-border group-data-[collapsible=icon]:px-2">
                {workspace?.current && (
                    <SidebarMenu>
                        <SidebarMenuItem>
                            <SidebarPlanUsage
                                plan={workspace.current.plan}
                                role={workspace.current.role}
                                usage={workspace.current.usage}
                                onBillingClick={() => {
                                    if (isMobile) {
                                        setOpenMobile(false);
                                    }
                                }}
                            />
                        </SidebarMenuItem>
                    </SidebarMenu>
                )}
                <SidebarIdentity roleLabel={workspace?.current?.role} />
            </SidebarFooter>
        </Sidebar>
    );
}
