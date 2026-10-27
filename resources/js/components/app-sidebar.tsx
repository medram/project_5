import { Link, usePage } from '@inertiajs/react';
import { CreditCard } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { WorkspaceSwitcher } from '@/components/workspaces/workspace-switcher';
import { Separator } from '@/components/ui/separator';
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
import { index as billingIndex } from '@/routes/billing';
import { index as linksIndex } from '@/routes/links';
import { workspaceNavSections } from '@/components/workspace-navigation';
import type { WorkspaceContext } from '@/types/workspaces';

export function AppSidebar() {
    const { isMobile, setOpenMobile } = useSidebar();
    const { workspace } = usePage<{ workspace?: WorkspaceContext }>().props;
    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link
                                href={linksIndex()}
                                prefetch
                                onClick={() => {
                                    if (isMobile) {
                                        setOpenMobile(false);
                                    }
                                }}
                            >
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain sections={workspaceNavSections} />
            </SidebarContent>
            <SidebarFooter className="gap-3">
                <WorkspaceSwitcher />
                {workspace?.current && (
                    <section
                        aria-label={`${workspace.current.plan} plan usage`}
                        className="space-y-2 rounded-lg border p-3 group-data-[collapsible=icon]:hidden"
                    >
                        <div className="flex items-center justify-between gap-2">
                            <div className="min-w-0">
                                <p className="text-xs text-muted-foreground">
                                    Current plan
                                </p>
                                <p className="truncate text-sm font-medium">
                                    {workspace.current.plan}
                                </p>
                            </div>
                            <SidebarMenuButton
                                asChild
                                size="sm"
                                tooltip="Manage plan"
                                className="min-h-11"
                            >
                                <Link
                                    href={billingIndex()}
                                    aria-label="Upgrade or manage plan"
                                    onClick={() => {
                                        if (isMobile) {
                                            setOpenMobile(false);
                                        }
                                    }}
                                >
                                    <CreditCard aria-hidden="true" />
                                    <span>Manage plan</span>
                                </Link>
                            </SidebarMenuButton>
                        </div>
                        <ul className="space-y-1 text-xs text-muted-foreground">
                            {Object.entries(workspace.current.usage).map(
                                ([key, metric]) => (
                                    <li
                                        key={key}
                                        className="flex justify-between gap-2"
                                    >
                                        <span className="truncate">
                                            {metric.label}
                                        </span>
                                        <span className="shrink-0 tabular-nums">
                                            {metric.used} /{' '}
                                            {metric.limit ?? 'Unlimited'}
                                        </span>
                                    </li>
                                ),
                            )}
                        </ul>
                    </section>
                )}
                <Separator />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
