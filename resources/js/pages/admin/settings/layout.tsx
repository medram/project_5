import { Link, router } from '@inertiajs/react';
import {
    BadgeCheck, BarChart3, Check, ChevronsUpDown, ContactRound, CreditCard,
    KeyRound, Languages, Link2, Mail, Megaphone, Menu, Palette, PanelsTopLeft,
    Search, Server, Settings2, Shield, ShieldCheck, Upload,
} from 'lucide-react';
import { useState } from 'react';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { cn } from '@/lib/utils';
import settings from '@/routes/admin/settings';
import type {
    AdminSettingsCategory,
    AdminSettingsNavigationItem,
} from '@/types/admin-settings';

const categories: Array<{ key: AdminSettingsCategory; label: string }> = [
    { key: 'platform', label: 'Basics' },
    { key: 'product', label: 'Product' },
    { key: 'access', label: 'Access & privacy' },
    { key: 'revenue', label: 'Revenue' },
    { key: 'operations', label: 'Operations' },
];

const sectionIcons: Record<string, typeof Settings2> = {
    general: Settings2,
    links: Link2,
    biolinks: ContactRound,
    landing: PanelsTopLeft,
    themes: Palette,
    menus: Menu,
    subscriptions: CreditCard,
    localization: Languages,
    authentication: KeyRound,
    uploading: Upload,
    email: Mail,
    system: Server,
    analytics: BarChart3,
    'brand-verification': BadgeCheck,
    captcha: ShieldCheck,
    gdpr: Shield,
    seo: Search,
    ads: Megaphone,
};

const grouped = (navigation: AdminSettingsNavigationItem[]) =>
    categories
        .map((category) => ({
            ...category,
            items: navigation.filter((item) => item.category === category.key),
        }))
        .filter((category) => category.items.length > 0);

export default function SettingsLayout({
    section,
    navigation,
    children,
}: {
    section: string;
    navigation: AdminSettingsNavigationItem[];
    children: React.ReactNode;
}) {
    const [open, setOpen] = useState(false);
    const current = navigation.find((item) => item.key === section);
    const CurrentIcon = sectionIcons[section] ?? Settings2;

    return (
        <div className="mx-auto flex w-full max-w-7xl min-w-0 flex-1 flex-col gap-5 px-5 pt-4 pb-10">
            <PageHeader
                title="Platform settings"
                description="Product and integration configuration shared by every workspace."
            />

            <div className="grid min-w-0 gap-6 lg:grid-cols-[230px_minmax(0,1fr)]">
                <div className="lg:hidden">
                    <Popover open={open} onOpenChange={setOpen}>
                        <PopoverTrigger asChild>
                            <Button
                                variant="outline"
                                className="min-h-11 w-full justify-between"
                                aria-label={`Choose settings section: ${current?.label ?? 'Settings'}`}
                            >
                                <span className="flex items-center gap-2">
                                    <CurrentIcon className="size-4" aria-hidden="true" />
                                    {current?.label ?? 'Settings'}
                                </span>
                                <ChevronsUpDown aria-hidden="true" />
                            </Button>
                        </PopoverTrigger>
                        <PopoverContent
                            align="start"
                            className="w-[min(22rem,calc(100vw-2rem))] p-0"
                        >
                            <Command>
                                <CommandInput placeholder="Search settings…" aria-label="Search settings sections" />
                                <CommandList>
                                    <CommandEmpty>
                                        No section found.
                                    </CommandEmpty>
                                    {grouped(navigation).map((category) => (
                                        <CommandGroup
                                            key={category.key}
                                            heading={category.label}
                                        >
                                            {category.items.map((item) => {
                                                const Icon = sectionIcons[item.key] ?? Settings2;
                                                return (
                                                    <CommandItem
                                                        key={item.key}
                                                        value={`${item.label} ${item.key}`}
                                                        data-active={item.key === section}
                                                        aria-current={item.key === section ? 'page' : undefined}
                                                        onSelect={() => {
                                                            setOpen(false);
                                                            router.visit(settings.show(item.key).url);
                                                        }}
                                                    >
                                                        <Icon className="size-4" aria-hidden="true" />
                                                        <span className="flex-1">
                                                            {item.label}
                                                            {item.key === section ? <span className="sr-only"> (current section)</span> : null}
                                                        </span>
                                                        {item.key === section ? <Check className="size-4" aria-hidden="true" /> : null}
                                                    </CommandItem>
                                                );
                                            })}
                                        </CommandGroup>
                                    ))}
                                </CommandList>
                            </Command>
                        </PopoverContent>
                    </Popover>
                </div>

                <nav
                    className="hidden flex-col gap-2 self-start lg:flex"
                    aria-label="Settings sections"
                >
                    {grouped(navigation).map((category) => (
                        <div key={category.key} className="flex flex-col">
                            <p className="mb-1 px-3 text-xs font-medium text-muted-foreground">
                                {category.label}
                            </p>
                            {category.items.map((item) => {
                                const Icon = sectionIcons[item.key] ?? Settings2;
                                return (
                                    <Link
                                        key={item.key}
                                        href={settings.show(item.key).url}
                                        className={cn(
                                            'flex min-h-9 items-center gap-2 rounded-md px-3 py-1.5 text-sm transition-colors focus-visible:ring-2 focus-visible:ring-ring/40 focus-visible:outline-none [@media(min-height:1050px)]:min-h-10',
                                            item.key === section
                                                ? 'bg-muted font-medium text-foreground'
                                                : 'text-muted-foreground hover:bg-muted/60 hover:text-foreground',
                                        )}
                                        aria-current={item.key === section ? 'page' : undefined}
                                    >
                                        <Icon className="size-4 shrink-0" aria-hidden="true" />
                                        {item.label}
                                    </Link>
                                );
                            })}
                        </div>
                    ))}
                </nav>

                <div className="min-w-0">{children}</div>
            </div>
        </div>
    );
}
