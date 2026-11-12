import { usePage } from '@inertiajs/react';
import {
    BarChart3,
    CreditCard,
    BookOpenText,
    FileText,
    Folder,
    FolderOpen,
    Globe2,
    Link2,
    PanelsTopLeft,
    ReceiptText,
    ScanLine,
    ShieldCheck,
    UserRound,
    Users,
} from 'lucide-react';
import {
    SidebarContent,
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import users from '@/routes/admin/users';
import roles from '@/routes/admin/roles';
import insights from '@/routes/admin/insights';
import plans from '@/routes/admin/plans';
import subscriptions from '@/routes/admin/subscriptions';
import content from '@/routes/admin/content';
import files from '@/routes/admin/files';
import pages from '@/routes/admin/pages';

export default function AdminSidebar() {
    const { url, props } = usePage();
    const { isMobile, setOpenMobile } = useSidebar();
    const abilities = props.auth.adminAbilities;
    const currentPath = url.split('?')[0];

    const items = [
        {
            label: 'Users',
            href: users.index().url,
            icon: Users,
            visible: abilities.includes('admin.users.view'),
        },
        {
            label: 'Files',
            href: files.index().url,
            icon: FolderOpen,
            visible: abilities.includes('admin.files.view'),
        },
        {
            label: 'Custom pages',
            href: pages.index().url,
            icon: BookOpenText,
            visible: abilities.includes('admin.pages.manage'),
        },
        {
            label: 'Links',
            href: content.links().url,
            icon: Link2,
            visible: abilities.includes('admin.content.view_metadata'),
        },
        {
            label: 'Bio pages',
            href: content.biolinks().url,
            icon: UserRound,
            visible: abilities.includes('admin.content.view_metadata'),
        },
        {
            label: 'Folders',
            href: content.folders().url,
            icon: Folder,
            visible: abilities.includes('admin.content.view_metadata'),
        },
        {
            label: 'Domains',
            href: content.domains().url,
            icon: Globe2,
            visible: abilities.includes('admin.content.view_metadata'),
        },
        {
            label: 'Overlays',
            href: content.overlays().url,
            icon: PanelsTopLeft,
            visible: abilities.includes('admin.content.view_metadata'),
        },
        {
            label: 'Link pages',
            href: content.pages().url,
            icon: FileText,
            visible: abilities.includes('admin.content.view_metadata'),
        },
        {
            label: 'Pixels',
            href: content.pixels().url,
            icon: ScanLine,
            visible: abilities.includes('admin.content.view_metadata'),
        },
        {
            label: 'Plans',
            href: plans.index().url,
            icon: CreditCard,
            visible: abilities.includes('admin.billing.view'),
        },
        {
            label: 'Subscriptions',
            href: subscriptions.index().url,
            icon: ReceiptText,
            visible: abilities.includes('admin.billing.view'),
        },
        {
            label: 'Insights',
            href: insights.index().url,
            icon: BarChart3,
            visible: abilities.includes('admin.insights.view'),
        },
        {
            label: 'Roles',
            href: roles.index().url,
            icon: ShieldCheck,
            visible: abilities.includes('admin.users.manage_roles'),
        },
    ].filter((item) => item.visible);

    return (
        <SidebarContent>
            <SidebarGroup className="px-2 py-0">
                <SidebarGroupLabel>Administration</SidebarGroupLabel>
                <SidebarMenu>
                    {items.map((item) => {
                        const Icon = item.icon;
                        const active =
                            currentPath === item.href ||
                            currentPath.startsWith(`${item.href}/`);

                        return (
                            <SidebarMenuItem key={item.label}>
                                <SidebarMenuButton
                                    asChild
                                    isActive={active}
                                    tooltip={item.label}
                                >
                                    <a
                                        href={item.href}
                                        aria-current={
                                            active ? 'page' : undefined
                                        }
                                        onClick={() => {
                                            if (isMobile) {
                                                setOpenMobile(false);
                                            }
                                        }}
                                    >
                                        <Icon aria-hidden="true" />
                                        <span>{item.label}</span>
                                    </a>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        );
                    })}
                </SidebarMenu>
            </SidebarGroup>
        </SidebarContent>
    );
}
