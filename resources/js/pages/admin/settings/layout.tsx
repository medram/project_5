import { Link, router } from '@inertiajs/react';
import { ChevronsUpDown, Settings2 } from 'lucide-react';
import { useState } from 'react';
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

    return (
        <div className="mx-auto flex w-full max-w-7xl min-w-0 flex-1 flex-col gap-5 px-5 pt-4 pb-10">
            <header className="flex flex-col gap-1">
                <h1 className="text-2xl leading-8 font-semibold tracking-[-0.02em]">
                    Platform settings
                </h1>
                <p className="max-w-3xl text-sm text-muted-foreground">
                    Product and integration configuration shared by every
                    workspace.
                </p>
            </header>

            <div className="grid min-w-0 gap-6 lg:grid-cols-[230px_minmax(0,1fr)]">
                <div className="lg:hidden">
                    <Popover open={open} onOpenChange={setOpen}>
                        <PopoverTrigger asChild>
                            <Button
                                variant="outline"
                                className="min-h-11 w-full justify-between"
                                aria-label="Choose settings section"
                            >
                                <span className="flex items-center gap-2">
                                    <Settings2 aria-hidden="true" />
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
                                <CommandInput placeholder="Search settings…" />
                                <CommandList>
                                    <CommandEmpty>
                                        No section found.
                                    </CommandEmpty>
                                    {grouped(navigation).map((category) => (
                                        <CommandGroup
                                            key={category.key}
                                            heading={category.label}
                                        >
                                            {category.items.map((item) => (
                                                <CommandItem
                                                    key={item.key}
                                                    value={`${item.label} ${item.key}`}
                                                    data-active={
                                                        item.key === section
                                                    }
                                                    onSelect={() => {
                                                        setOpen(false);
                                                        router.visit(
                                                            settings.show(
                                                                item.key,
                                                            ).url,
                                                        );
                                                    }}
                                                >
                                                    {item.label}
                                                </CommandItem>
                                            ))}
                                        </CommandGroup>
                                    ))}
                                </CommandList>
                            </Command>
                        </PopoverContent>
                    </Popover>
                </div>

                <nav
                    className="sticky top-20 hidden h-fit flex-col gap-5 self-start lg:flex"
                    aria-label="Settings sections"
                >
                    {grouped(navigation).map((category) => (
                        <div key={category.key} className="flex flex-col gap-1">
                            <p className="px-3 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                {category.label}
                            </p>
                            {category.items.map((item) => (
                                <Link
                                    key={item.key}
                                    href={settings.show(item.key).url}
                                    preserveScroll
                                    className={cn(
                                        'rounded-md px-3 py-1.5 text-sm transition-colors',
                                        item.key === section
                                            ? 'bg-muted font-medium text-foreground'
                                            : 'text-muted-foreground hover:bg-muted/60 hover:text-foreground',
                                    )}
                                    aria-current={
                                        item.key === section
                                            ? 'page'
                                            : undefined
                                    }
                                >
                                    {item.label}
                                </Link>
                            ))}
                        </div>
                    ))}
                </nav>

                <div className="min-w-0">{children}</div>
            </div>
        </div>
    );
}
