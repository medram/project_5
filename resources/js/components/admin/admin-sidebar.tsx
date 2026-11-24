import { Link, usePage } from '@inertiajs/react';
import {
    BarChart3,
    CreditCard,
    BookOpenText,
    FileText,
    Folder,
    FolderOpen,
    Globe2,
    Languages,
    Link2,
    PanelsTopLeft,
    QrCode,
    ReceiptText,
    ScanLine,
    ScrollText,
    Settings2,
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
import localizations from '@/routes/admin/localizations';
import logs from '@/routes/admin/logs';
import pages from '@/routes/admin/pages';
import settings from '@/routes/admin/settings';

export default function AdminSidebar() {
    const { url, props } = usePage();
    const { isMobile, setOpenMobile } = useSidebar();
    const abilities = props.auth.adminAbilities;
    const currentPath = url.split('?')[0];

    const can = (ability: string) => abilities.includes(ability);

    const sections = [
        {
            title: 'Overview',
            items: [
                {
                    label: 'Insights',
                    href: insights.index().url,
                    icon: BarChart3,
                    visible: can('admin.insights.view'),
                },
            ],
        },
        {
            title: 'Accounts',
            items: [
                {
                    label: 'Users',
                    href: users.index().url,
                    icon: Users,
                    visible: can('admin.users.view'),
                },
                {
                    label: 'Roles',
                    href: roles.index().url,
                    icon: ShieldCheck,
                    visible: can('admin.users.manage_roles'),
                },
            ],
        },
        {
            title: 'Content',
            items: [
                {
                    label: 'Short links',
                    href: content.links().url,
                    icon: Link2,
                    visible: can('admin.content.links.view'),
                },
                {
                    label: 'Bio pages',
                    href: content.biolinks().url,
                    icon: UserRound,
                    visible: can('admin.content.biolinks.view'),
                },
                {
                    label: 'Link pages',
                    href: content.pages().url,
                    icon: FileText,
                    visible: can('admin.content.link_pages.view'),
                },
                {
                    label: 'QR codes',
                    href: '/admin/qr-codes',
                    icon: QrCode,
                    visible: can('admin.content.qr_codes.view'),
                },
                {
                    label: 'Folders',
                    href: content.folders().url,
                    icon: Folder,
                    visible: can('admin.content.folders.view'),
                },
                {
                    label: 'Domains',
                    href: content.domains().url,
                    icon: Globe2,
                    visible: can('admin.content.domains.view'),
                },
                {
                    label: 'Overlays',
                    href: content.overlays().url,
                    icon: PanelsTopLeft,
                    visible: can('admin.content.overlays.view'),
                },
                {
                    label: 'Pixels',
                    href: content.pixels().url,
                    icon: ScanLine,
                    visible: can('admin.content.pixels.view'),
                },
                {
                    label: 'Custom pages',
                    href: pages.index().url,
                    icon: BookOpenText,
                    visible: can('admin.pages.manage'),
                },
                {
                    label: 'Files',
                    href: files.index().url,
                    icon: FolderOpen,
                    visible: can('admin.files.view'),
                },
            ],
        },
        {
            title: 'Monetization',
            items: [
                {
                    label: 'Plans',
                    href: plans.index().url,
                    icon: CreditCard,
                    visible: can('admin.billing.view'),
                },
                {
                    label: 'Subscriptions',
                    href: subscriptions.index().url,
                    icon: ReceiptText,
                    visible: can('admin.billing.view'),
                },
            ],
        },
        {
            title: 'System',
            items: [
                {
                    label: 'Settings',
                    href: settings.show('general').url,
                    icon: Settings2,
                    visible: can('admin.settings.view'),
                },
                {
                    label: 'Localizations',
                    href: localizations.index().url,
                    icon: Languages,
                    visible: can('admin.localizations.manage'),
                },
                {
                    label: 'Operation log',
                    href: logs.index().url,
                    icon: ScrollText,
                    visible: can('admin.logs.view'),
                },
            ],
        },
    ]
        .map((section) => ({
            ...section,
            items: section.items.filter((item) => item.visible),
        }))
        .filter((section) => section.items.length > 0);

    return (
        <SidebarContent className="px-2 pb-2">
            {sections.map((section) => (
                <SidebarGroup
                    key={section.title}
                    className="mt-4 px-0 py-0 first:mt-0"
                >
                    <SidebarGroupLabel className="mb-0.5">
                        {section.title}
                    </SidebarGroupLabel>
                    <SidebarMenu>
                        {section.items.map((item) => {
                            const Icon = item.icon;
                            const active =
                                currentPath === item.href ||
                                currentPath.startsWith(`${item.href}/`);
                            const current = currentPath === item.href;

                            return (
                                <SidebarMenuItem key={item.label}>
                                    <SidebarMenuButton
                                        asChild
                                        isActive={active}
                                        tooltip={item.label}
                                        className="h-10 max-md:h-11"
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
                                            <Icon aria-hidden="true" />
                                            <span>{item.label}</span>
                                        </Link>
                                    </SidebarMenuButton>
                                </SidebarMenuItem>
                            );
                        })}
                    </SidebarMenu>
                </SidebarGroup>
            ))}
        </SidebarContent>
    );
}
