import { usePage } from '@inertiajs/react';
import { ChevronsUpDown } from 'lucide-react';
import { useRef, useState } from 'react';
import { HelpDialog } from '@/components/help-dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { UserAvatar } from '@/components/user-avatar';
import { UserMenuContent } from '@/components/user-menu-content';

export function SidebarIdentity({ roleLabel }: { roleLabel?: string }) {
    const { auth } = usePage().props;
    const { isMobile, state, setOpenMobile } = useSidebar();
    const [helpOpen, setHelpOpen] = useState(false);
    const identityRef = useRef<HTMLButtonElement>(null);

    if (!auth.user) {
        return null;
    }

    return (
        <SidebarMenu>
            <SidebarMenuItem>
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <SidebarMenuButton
                            ref={identityRef}
                            size="lg"
                            className="h-auto gap-3 px-2 py-1.5 data-[state=open]:bg-sidebar-accent"
                        >
                            <UserAvatar
                                name={auth.user.name}
                                src={auth.user.avatar}
                                seed={auth.user.email}
                                className="size-10 group-data-[collapsible=icon]:size-8"
                            />
                            <div className="grid min-w-0 flex-1 text-left leading-tight">
                                <span className="truncate text-sm font-semibold">
                                    {auth.user.name}
                                </span>
                                <span className="truncate text-xs font-normal text-muted-foreground capitalize">
                                    {roleLabel ?? 'Member'}
                                </span>
                            </div>
                            <ChevronsUpDown
                                className="size-4 shrink-0 text-muted-foreground"
                                aria-hidden="true"
                            />
                        </SidebarMenuButton>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent
                        className="w-(--radix-dropdown-menu-trigger-width) min-w-56 rounded-xl"
                        align="end"
                        side={!isMobile && state === 'collapsed' ? 'right' : 'top'}
                    >
                        <UserMenuContent
                            onHelp={() => setHelpOpen(true)}
                            user={auth.user}
                            onNavigate={() => {
                                if (isMobile) {
                                    setOpenMobile(false);
                                }
                            }}
                        />
                    </DropdownMenuContent>
                </DropdownMenu>
            </SidebarMenuItem>
            <HelpDialog
                open={helpOpen}
                onOpenChange={setHelpOpen}
                onCloseAutoFocus={(event) => {
                    event.preventDefault();
                    identityRef.current?.focus();
                }}
            />
        </SidebarMenu>
    );
}
