import { Link, router } from '@inertiajs/react';
import { Settings2 } from 'lucide-react';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import settings from '@/routes/admin/settings';
import type { AdminSettingsNavigationItem } from '@/types/admin-settings';

export default function SettingsLayout({
    section,
    navigation,
    children,
}: {
    section: string;
    navigation: AdminSettingsNavigationItem[];
    children: React.ReactNode;
}) {
    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <header className="space-y-1">
                <div className="flex items-center gap-2 text-sm text-muted-foreground">
                    <Settings2 className="size-4" aria-hidden="true" />
                    <span>Platform administration</span>
                </div>
                <h1 className="text-2xl font-semibold tracking-tight">
                    Platform settings
                </h1>
                <p className="max-w-3xl text-sm text-muted-foreground">
                    Code-owned settings registry with typed values, optimistic
                    versions, secret encryption, and environment precedence.
                </p>
            </header>

            <div className="grid min-w-0 gap-6 lg:grid-cols-[220px_minmax(0,1fr)]">
                <div className="lg:hidden">
                    <Select
                        value={section}
                        onValueChange={(value) =>
                            router.visit(settings.show(value).url)
                        }
                    >
                        <SelectTrigger className="min-h-11 w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {navigation.map((item) => (
                                <SelectItem key={item.key} value={item.key}>
                                    {item.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>
                <nav
                    className="hidden h-fit flex-col gap-1 lg:flex"
                    aria-label="Settings sections"
                >
                    {navigation.map((item) => (
                        <Link
                            key={item.key}
                            href={settings.show(item.key).url}
                            preserveScroll
                            className={`rounded-md px-3 py-2 text-sm ${
                                item.key === section
                                    ? 'bg-muted font-medium text-foreground'
                                    : 'text-muted-foreground hover:bg-muted/60 hover:text-foreground'
                            }`}
                            aria-current={
                                item.key === section ? 'page' : undefined
                            }
                        >
                            {item.label}
                        </Link>
                    ))}
                </nav>
                <div className="min-w-0">{children}</div>
            </div>
        </div>
    );
}
