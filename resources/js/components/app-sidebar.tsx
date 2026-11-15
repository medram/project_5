import { Link, usePage } from '@inertiajs/react';
import { HelpCircle, LogOut } from 'lucide-react';
import { HelpDialog } from '@/components/help-dialog';
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
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { filteredWorkspaceNavSections } from '@/components/workspace-navigation';
import { logout } from '@/routes';
import type { WorkspaceContext } from '@/types/workspaces';

export function AppSidebar() {
    const { isMobile, setOpenMobile } = useSidebar();
    const { workspace, platform } = usePage<{ workspace?: WorkspaceContext }>()
        .props;

    return (
        <Sidebar collapsible="icon" variant="sidebar">
            <SidebarHeader className="gap-1.5 p-3 pb-1">
                <SidebarIdentity roleLabel={workspace?.current?.role} />
                <WorkspaceSwitcher />
            </SidebarHeader>

            <SidebarContent className="px-2 pb-2">
                <NavMain
                    sections={filteredWorkspaceNavSections(
                        platform?.capabilities,
                    )}
                />
            </SidebarContent>

            <SidebarFooter className="gap-1 p-3 pt-1 max-lg:border-t max-lg:border-sidebar-border">
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
                <SidebarMenu>
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
                                onClick={() => {
                                    if (isMobile) {
                                        setOpenMobile(false);
                                    }
                                }}
                            >
                                <LogOut aria-hidden="true" />
                                <span>Log out</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarFooter>
        </Sidebar>
    );
}
