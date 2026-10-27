import { Link } from '@inertiajs/react';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import type { NavItem } from '@/types';

type Section = {
    title: string;
    items: NavItem[];
};

export function NavMain({ sections }: { sections: Section[] }) {
    const { isCurrentOrParentUrl, isCurrentUrl } = useCurrentUrl();
    const { isMobile, setOpenMobile } = useSidebar();

    return (
        <>
            {sections.map((section) => (
                <SidebarGroup key={section.title} className="px-2 py-0">
                    <SidebarGroupLabel>{section.title}</SidebarGroupLabel>
                    <SidebarMenu>
                        {section.items.map((item) => {
                            const active = isCurrentOrParentUrl(item.href);
                            const current = active && isCurrentUrl(item.href);

                            return (
                                <SidebarMenuItem key={item.title}>
                                    <SidebarMenuButton
                                        asChild
                                        className="min-h-11"
                                        isActive={active}
                                        tooltip={{ children: item.title }}
                                    >
                                        <Link
                                            href={item.href}
                                            prefetch
                                            aria-current={
                                                active
                                                    ? current
                                                        ? 'page'
                                                        : 'location'
                                                    : undefined
                                            }
                                            onClick={() => {
                                                if (isMobile) {
                                                    setOpenMobile(false);
                                                }
                                            }}
                                        >
                                            {item.icon && <item.icon />}
                                            <span>{item.title}</span>
                                        </Link>
                                    </SidebarMenuButton>
                                </SidebarMenuItem>
                            );
                        })}
                    </SidebarMenu>
                </SidebarGroup>
            ))}
        </>
    );
}
